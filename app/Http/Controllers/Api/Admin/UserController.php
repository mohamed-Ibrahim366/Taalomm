<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    /**
     * GET /api/admin/users
     * Paginated, filterable, sortable user list.
     * Query params: search, role, status, trashed (with|only), sort_by, sort_dir, per_page.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $paginator = $this->userService->list(
            $request->only(['search', 'role', 'status', 'trashed', 'sort_by', 'sort_dir', 'per_page'])
        );

        return response()->json(
            UserResource::collection($paginator)->response()->getData(true)
        );
    }

    /**
     * POST /api/admin/users
     * Create an admin or teacher account.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'User created successfully.',
            'user'    => new UserResource($user),
        ], 201);
    }

    /**
     * GET /api/admin/users/{user}
     * Show a single user's details.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * PUT /api/admin/users/{user}
     * Update a user's account fields.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->userService->update($user, $request->validated(), $request->user());

        return response()->json([
            'message' => 'User updated successfully.',
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * DELETE /api/admin/users/{user}
     * Soft-delete a user account.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->userService->delete($user, $request->user());

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * POST /api/admin/users/{id}/restore
     * Restore a soft-deleted user. Uses {id} directly since soft-deleted
     * records are excluded from the default route-model binding scope.
     */
    public function restore(Request $request, int $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        $this->authorize('restore', $user);

        $user = $this->userService->restore($user, $request->user());

        return response()->json([
            'message' => 'User restored successfully.',
            'user'    => new UserResource($user),
        ]);
    }

    /**
     * PUT /api/admin/users/{user}/status
     * Change a user's status (activate / suspend / deactivate).
     */
    public function changeStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $this->authorize('changeStatus', $user);

        $user = $this->userService->changeStatus(
            $user,
            UserStatus::from($request->validated('status')),
            $request->user(),
        );

        return response()->json([
            'message' => 'User status updated successfully.',
            'user'    => new UserResource($user),
        ]);
    }
}
