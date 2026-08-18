<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Models\User;
use App\Notifications\SendOtpNotification;
use App\Services\OtpService;

class EmailVerificationController extends Controller
{
    public function __construct(private readonly OtpService $otp)
    {
    }

    /**
     * Send (or resend) a 6-digit verification code to the user's email.
     */
    public function send(SendOtpRequest $request)
    {
        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'This email is already verified.'], 422);
        }

        $code = $this->otp->generate($user->email, OtpPurpose::EMAIL_VERIFICATION);

        $user->notify(new SendOtpNotification($code, OtpPurpose::EMAIL_VERIFICATION));

        return response()->json(['message' => 'Verification code sent to your email.']);
    }

    /**
     * Verify the code and activate the account.
     */
    public function verify(VerifyEmailRequest $request)
    {
        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'This email is already verified.'], 422);
        }

        $valid = $this->otp->verify($user->email, OtpPurpose::EMAIL_VERIFICATION, $request->otp);

        if (! $valid) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ])->save();

        return response()->json(['message' => 'Email verified successfully.']);
    }
}
