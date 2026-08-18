<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\ConfirmEmailChangeRequest;
use App\Http\Requests\Profile\UpdateEmailRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadPhotoRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profileService) {}

    /**
     * GET /api/profile
     * Return the authenticated user's full profile.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * PUT /api/profile
     * Update name and/or phone number.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->update(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * POST /api/profile/photo
     * Upload or replace the profile photo.
     */
    public function uploadPhoto(UploadPhotoRequest $request): JsonResponse
    {
        $user = $this->profileService->uploadPhoto(
            $request->user(),
            $request->file('photo'),
        );

        return response()->json([
            'message' => 'Photo uploaded successfully.',
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * DELETE /api/profile/photo
     * Remove the current profile photo.
     */
    public function deletePhoto(Request $request): JsonResponse
    {
        $user = $this->profileService->deletePhoto($request->user());

        return response()->json([
            'message' => 'Photo removed successfully.',
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * PUT /api/profile/password
     * Change password — keeps the current session alive, revokes all others.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->profileService->changePassword(
            $request->user(),
            $request->validated('password'),
            $request->user()->currentAccessToken()?->id,
        );

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * PUT /api/profile/email
     * Step 1: validate the new email + current password, then send OTP to new address.
     */
    public function initiateEmailChange(UpdateEmailRequest $request): JsonResponse
    {
        $this->profileService->initiateEmailChange(
            $request->user(),
            $request->validated('email'),
        );

        return response()->json([
            'message' => 'A verification code has been sent to your new email address. It expires in 30 minutes.',
        ]);
    }

    /**
     * POST /api/profile/email/verify
     * Step 2: verify the OTP and commit the email change.
     */
    public function confirmEmailChange(ConfirmEmailChangeRequest $request): JsonResponse
    {
        $user = $this->profileService->confirmEmailChange(
            $request->user(),
            $request->validated('otp'),
        );

        return response()->json([
            'message' => 'Email address updated successfully.',
            'user'    => new UserResource($user),
        ]);
    }
}
