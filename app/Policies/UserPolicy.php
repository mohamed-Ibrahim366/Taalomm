<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admins bypass all policy checks — they have unrestricted access.
     */
    public function before(User $authUser, string $ability): bool|null
    {
        // Returning null falls through to the specific policy method.
        // Returning true would short-circuit and grant everything to admins,
        // but we intentionally exclude that so admins cannot delete themselves.
        return null;
    }

    /**
     * List all users (admin + teacher can view the user list).
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->isAdmin() || $authUser->isTeacher();
    }

    /**
     * View a single user's details.
     * Admins can view anyone. Users can only view themselves.
     */
    public function view(User $authUser, User $user): bool
    {
        return $authUser->isAdmin() || $authUser->id === $user->id;
    }

    /**
     * Create new user accounts (admin only).
     */
    public function create(User $authUser): bool
    {
        return $authUser->isAdmin();
    }

    /**
     * Update a user's account data.
     * Admins can update anyone. Users can only update their own profile.
     */
    public function update(User $authUser, User $user): bool
    {
        return $authUser->isAdmin() || $authUser->id === $user->id;
    }

    /**
     * Soft-delete a user. Admins only, and cannot delete themselves.
     */
    public function delete(User $authUser, User $user): bool
    {
        return $authUser->isAdmin() && $authUser->id !== $user->id;
    }

    /**
     * Restore a soft-deleted user. Admins only.
     */
    public function restore(User $authUser, User $user): bool
    {
        return $authUser->isAdmin();
    }

    /**
     * Change a user's status (activate/suspend/deactivate).
     * Admins only, and cannot change their own status.
     */
    public function changeStatus(User $authUser, User $user): bool
    {
        return $authUser->isAdmin() && $authUser->id !== $user->id;
    }

    /**
     * Hard delete is permanently disabled; no one may force-delete users.
     */
    public function forceDelete(User $authUser, User $user): bool
    {
        return false;
    }
}
