<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    private const CODE_TTL_MINUTES = 10;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * Generate a new OTP, store its hash in cache, and return the plain code
     * so the caller can send it via mail/SMS. Throws if still in cooldown.
     */
    public function generate(string $email, OtpPurpose $purpose): string
    {
        $this->assertNotInCooldown($email, $purpose);

        $code = (string) random_int(100000, 999999);

        Cache::put(
            $this->codeKey($email, $purpose),
            ['hash' => Hash::make($code), 'attempts' => 0],
            now()->addMinutes(self::CODE_TTL_MINUTES)
        );

        $expiresAt = now()->addSeconds(self::RESEND_COOLDOWN_SECONDS);
        Cache::put($this->cooldownKey($email, $purpose), $expiresAt, $expiresAt);

        return $code;
    }

    /**
     * Verify a submitted code. Returns true/false and burns an attempt on
     * every check to prevent brute-forcing a 6-digit code.
     */
    public function verify(string $email, OtpPurpose $purpose, string $code): bool
    {
        $key = $this->codeKey($email, $purpose);
        $data = Cache::get($key);

        if (! $data) {
            return false; // expired or never requested
        }

        if ($data['attempts'] >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        if (! Hash::check($code, $data['hash'])) {
            $data['attempts']++;
            Cache::put($key, $data, now()->addMinutes(self::CODE_TTL_MINUTES));

            return false;
        }

        // Correct code: consume it immediately (one-time use).
        Cache::forget($key);

        return true;
    }

    public function secondsUntilResendAllowed(string $email, OtpPurpose $purpose): int
    {
        $expiresAt = Cache::get($this->cooldownKey($email, $purpose));

        if (! $expiresAt) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($expiresAt, false));
    }

    private function assertNotInCooldown(string $email, OtpPurpose $purpose): void
    {
        if (Cache::has($this->cooldownKey($email, $purpose))) {
            abort(429, 'Please wait before requesting another code.');
        }
    }

    private function codeKey(string $email, OtpPurpose $purpose): string
    {
        return "otp:{$purpose->value}:{$email}";
    }

    private function cooldownKey(string $email, OtpPurpose $purpose): string
    {
        return "otp_cooldown:{$purpose->value}:{$email}";
    }
}
