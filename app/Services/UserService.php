<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Events\UserCreated;
use App\Events\UserDeleted;
use App\Events\UserRestored;
use App\Events\UserStatusChanged;
use App\Events\UserUpdated;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    private const SORTABLE_COLUMNS = [
        'name', 'email', 'role', 'status', 'created_at', 'last_login_at',
    ];

    private const MAX_PER_PAGE = 100;

    /**
     * Paginated, filtered, searchable, sortable user list for the admin panel.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $sortBy  = in_array($filters['sort_by'] ?? null, self::SORTABLE_COLUMNS, true)
            ? $filters['sort_by']
            : 'created_at';

        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), ['asc', 'desc'], true)
            ? strtolower($filters['sort_dir'])
            : 'desc';

        $perPage = min((int) ($filters['per_page'] ?? 15), self::MAX_PER_PAGE);

        return User::query()
            ->when(
                $filters['search'] ?? null,
                fn ($q, $s) => $q->where(
                    fn ($q) => $q->where('name', 'like', "%{$s}%")
                              ->orWhere('email', 'like', "%{$s}%")
                )
            )
            ->when($filters['role'] ?? null, fn ($q, $r) => $q->where('role', $r))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when(
                ($filters['trashed'] ?? null) === 'only',
                fn ($q) => $q->onlyTrashed(),
                fn ($q) => $q->when(
                    ($filters['trashed'] ?? null) === 'with',
                    fn ($q) => $q->withTrashed()
                )
            )
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    /**
     * Create an admin or teacher account (admin-side only).
     * Admin-created accounts are pre-verified and immediately active.
     */
    public function create(array $data, User $createdBy): User
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $user = User::create([
                'name'              => $data['name'],
                'email'             => $data['email'],
                'password'          => Hash::make($data['password']),
                'role'              => $data['role'],
                'phone'             => $data['phone'] ?? null,
                'status'            => UserStatus::ACTIVE,
                'email_verified_at' => now(),
            ]);

            event(new UserCreated($user, $createdBy));

            return $user;
        });
    }

    /**
     * Update a user's account fields (admin action).
     */
    public function update(User $user, array $data, User $updatedBy): User
    {
        return DB::transaction(function () use ($user, $data, $updatedBy) {
            $changes = array_keys(
                array_diff_assoc($data, $user->only(array_keys($data)))
            );

            $user->update($data);

            event(new UserUpdated($user->fresh(), $updatedBy, array_fill_keys($changes, true)));

            return $user->fresh();
        });
    }

    /**
     * Change a user's account status (activate, suspend, deactivate).
     * Suspending a user immediately revokes all of their tokens.
     */
    public function changeStatus(User $user, UserStatus $newStatus, User $changedBy): User
    {
        return DB::transaction(function () use ($user, $newStatus, $changedBy) {
            $oldStatus = $user->status;

            $user->update(['status' => $newStatus]);

            if ($newStatus === UserStatus::SUSPENDED || $newStatus === UserStatus::INACTIVE) {
                $user->tokens()->delete();
            }

            event(new UserStatusChanged($user->fresh(), $oldStatus, $newStatus, $changedBy));

            return $user->fresh();
        });
    }

    /**
     * Soft-delete a user and revoke all their active tokens.
     */
    public function delete(User $user, User $deletedBy): void
    {
        DB::transaction(function () use ($user, $deletedBy) {
            $user->tokens()->delete();
            $user->delete();

            event(new UserDeleted($user, $deletedBy));
        });
    }

    /**
     * Restore a previously soft-deleted user.
     */
    public function restore(User $user, User $restoredBy): User
    {
        return DB::transaction(function () use ($user, $restoredBy) {
            $user->restore();

            event(new UserRestored($user->fresh(), $restoredBy));

            return $user->fresh();
        });
    }
}
