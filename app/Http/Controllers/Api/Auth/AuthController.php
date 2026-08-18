<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\SendOtpNotification;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp)
    {
    }

    /**
     * Register a new student account.
     * Teacher/admin accounts are created via separate back-office flows.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => UserRole::STUDENT,
            'phone' => $request->phone,
            'governorate' => $request->governorate,
            'grades' => $request->grades,
            'status' => UserStatus::PENDING,
        ]);

        $code = $this->otp->generate($user->email, OtpPurpose::EMAIL_VERIFICATION);
        $user->notify(new SendOtpNotification($code, OtpPurpose::EMAIL_VERIFICATION));

        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        return response()->json([
            'message' => 'Registered successfully. A verification code was sent to your email.',
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }


    /**
     * Authenticate a user and issue a Sanctum token.
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->logActivity(null, 'failed_login', $request);

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if( $user->status === UserStatus::PENDING) {
            return response()->json(['message' => 'Your account is pending verification. Please check your email for the verification code.'], 403);
        }

        if ($user->status === UserStatus::SUSPENDED) {
            return response()->json(['message' => 'Your account has been suspended.'], 403);
        }

        if ($user->status === UserStatus::INACTIVE) {
            return response()->json(['message' => 'Your account is inactive. Please contact support.'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        $this->logActivity($user, 'login', $request);

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Revoke the current access token (logout from this device).
     */
    public function logout(\Illuminate\Http\Request $request)
    {
        $this->logActivity($request->user(), 'logout', $request);

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Revoke all tokens for the user (logout from all devices).
     */
    public function logoutAll(\Illuminate\Http\Request $request)
    {
        $this->logActivity($request->user(), 'logout', $request);

        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices.']);
    }

    /**
     * Return the authenticated user.
     */
    public function me(\Illuminate\Http\Request $request)
    {
        return new UserResource($request->user());
    }

    /**
     * Send a password reset OTP. Always returns the same generic response
     * to avoid account enumeration, regardless of whether the email exists.
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if ($user) {
            $code = $this->otp->generate($user->email, OtpPurpose::PASSWORD_RESET);
            $user->notify(new SendOtpNotification($code, OtpPurpose::PASSWORD_RESET));
        }

        return response()->json([
            'message' => 'If that email address is registered, a password reset code has been sent.',
        ]);
    }

    /**
     * Verify the OTP and set the new password, then revoke all existing tokens.
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        $user = User::where('email', $request->email)->firstOrFail();

        $valid = $this->otp->verify($user->email, OtpPurpose::PASSWORD_RESET, $request->otp);

        if (! $valid) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        // Invalidate all existing sessions/tokens after a password reset.
        $user->tokens()->delete();

        // Optional: notify the user their password was changed.
        // $user->notify(new \App\Notifications\PasswordChangedNotification());

        return response()->json(['message' => 'Password has been reset successfully.']);
    }

    private function logActivity(?User $user, string $event, \Illuminate\Http\Request $request): void
    {
        \App\Models\LoginActivity::create([
            'user_id' => $user?->id,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
