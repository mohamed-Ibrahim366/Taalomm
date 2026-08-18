<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Events\PasswordChanged;
use App\Events\ProfileUpdated;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\SendOtpNotification;

class ProfileService
{
    public function __construct(private readonly OtpService $otpService) {}

    /**
     * Update the user's basic profile fields (name, phone).
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $changed = array_keys(
                array_diff_assoc($data, $user->only(array_keys($data)))
            );

            $user->update($data);

            event(new ProfileUpdated($user->fresh(), array_fill_keys($changed, true)));

            return $user->fresh();
        });
    }

    /**
     * Store a new profile photo and delete the previous one.
     */
    public function uploadPhoto(User $user, UploadedFile $photo): User
    {
        return DB::transaction(function () use ($user, $photo) {
            if ($user->photo_path) {
                Storage::disk('public')->delete($user->photo_path);
            }

            $path = $photo->store('photos/users', 'public');
            $user->update(['photo_path' => $path]);

            event(new ProfileUpdated($user->fresh(), ['photo' => 'uploaded']));

            return $user->fresh();
        });
    }

    /**
     * Delete the user's profile photo.
     */
    public function deletePhoto(User $user): User
    {
        return DB::transaction(function () use ($user) {
            if ($user->photo_path) {
                Storage::disk('public')->delete($user->photo_path);
                $user->update(['photo_path' => null]);
                event(new ProfileUpdated($user->fresh(), ['photo' => 'deleted']));
            }

            return $user->fresh();
        });
    }

    /**
     * Change the user's password. Keeps the current session alive but revokes
     * all other active tokens (logs out other devices).
     */
    public function changePassword(User $user, string $newPassword, int|string|null $currentTokenId): void
    {
        DB::transaction(function () use ($user, $newPassword, $currentTokenId) {
            $user->update(['password' => Hash::make($newPassword)]);

            $user->tokens()
                ->when(
                    $currentTokenId !== null,
                    fn ($q) => $q->where('id', '!=', $currentTokenId),
                )
                ->delete();

            event(new PasswordChanged($user->fresh()));
        });
    }

    /**
     * Send an OTP to the new email address so the user can verify ownership
     * before we swap the email on the account.
     */
    public function initiateEmailChange(User $user, string $newEmail): void
    {
        // Cache the pending address so confirmEmailChange can retrieve it.
        Cache::put(
            "email_change:{$user->id}",
            $newEmail,
            now()->addMinutes(30),
        );

        $code = $this->otpService->generate($newEmail, OtpPurpose::EMAIL_CHANGE);

        // Send to the *new* email, not the current one.
        Notification::route('mail', $newEmail)
            ->notify(new SendOtpNotification($code, OtpPurpose::EMAIL_CHANGE));
    }

    /**
     * Verify the OTP and swap in the new email address.
     */
    public function confirmEmailChange(User $user, string $otp): User
    {
        $newEmail = Cache::get("email_change:{$user->id}");

        abort_if(! $newEmail, 422, 'No pending email change request found or it has expired.');

        $valid = $this->otpService->verify($newEmail, OtpPurpose::EMAIL_CHANGE, $otp);

        abort_if(! $valid, 422, 'Invalid or expired verification code.');

        return DB::transaction(function () use ($user, $newEmail) {
            Cache::forget("email_change:{$user->id}");

            $user->forceFill([
                'email'             => $newEmail,
                'email_verified_at' => now(),
            ])->save();

            event(new ProfileUpdated($user->fresh(), ['email' => $newEmail]));

            return $user->fresh();
        });
    }
}
