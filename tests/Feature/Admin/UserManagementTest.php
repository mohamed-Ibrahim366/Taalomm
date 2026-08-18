<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\UserCreated;
use App\Events\UserDeleted;
use App\Events\UserRestored;
use App\Events\UserStatusChanged;
use App\Events\UserUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    // ---- Helpers ----

    private function admin(): User
    {
        return User::factory()->create([
            'role'              => UserRole::ADMIN,
            'status'            => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function teacher(): User
    {
        return User::factory()->create([
            'role'              => UserRole::TEACHER,
            'status'            => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function student(): User
    {
        return User::factory()->create([
            'role'              => UserRole::STUDENT,
            'status'            => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    // ---- GET /api/admin/users ----

    public function test_admin_can_list_users(): void
    {
        $admin = $this->admin();
        User::factory()->count(5)->create(['role' => UserRole::STUDENT]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'data'  => [['id', 'name', 'email', 'role', 'status']],
                'links' => [],
                'meta'  => ['total', 'per_page', 'current_page'],
            ]);
    }

    public function test_teacher_can_list_users(): void
    {
        $this->actingAs($this->teacher(), 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk();
    }

    public function test_student_cannot_list_users(): void
    {
        $this->actingAs($this->student(), 'sanctum')
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_list_users(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_admin_can_search_users_by_name(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Alice Johnson', 'role' => UserRole::STUDENT]);
        User::factory()->create(['name' => 'Bob Smith',     'role' => UserRole::STUDENT]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?search=Alice')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Alice Johnson', $response->json('data.0.name'));
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create(['role' => UserRole::TEACHER]);
        User::factory()->count(2)->create(['role' => UserRole::STUDENT]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?role=teacher')
            ->assertOk();

        foreach ($response->json('data') as $user) {
            $this->assertEquals('teacher', $user['role']['value'] ?? $user['role']);
        }
    }

    public function test_admin_can_filter_users_by_status(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create(['status' => UserStatus::SUSPENDED]);
        User::factory()->count(3)->create(['status' => UserStatus::ACTIVE]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?status=suspended')
            ->assertOk();

        foreach ($response->json('data') as $user) {
            $this->assertEquals('suspended', $user['status']['value'] ?? $user['status']);
        }
    }

    public function test_admin_can_sort_users(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?sort_by=name&sort_dir=asc')
            ->assertOk();
    }

    public function test_admin_can_paginate_users(): void
    {
        $admin = $this->admin();
        User::factory()->count(20)->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users?per_page=5')
            ->assertOk();

        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(5, $response->json('meta.per_page'));
    }

    // ---- POST /api/admin/users ----

    public function test_admin_can_create_teacher_account(): void
    {
        Event::fake([UserCreated::class]);

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name'     => 'New Teacher',
                'email'    => 'newteacher@taalom.com',
                'password' => 'Teacher@12345',
                'role'     => 'teacher',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'User created successfully.')
            ->assertJsonPath('user.email', 'newteacher@taalom.com');

        $this->assertDatabaseHas('users', [
            'email'  => 'newteacher@taalom.com',
            'role'   => 'teacher',
            'status' => 'active',
        ]);

        Event::assertDispatched(UserCreated::class);
    }

    public function test_admin_can_create_admin_account(): void
    {
        Event::fake([UserCreated::class]);

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name'     => 'Another Admin',
                'email'    => 'admin2@taalom.com',
                'password' => 'Admin@12345',
                'role'     => 'admin',
            ])
            ->assertCreated();

        Event::assertDispatched(UserCreated::class);
    }

    public function test_cannot_create_student_via_admin_endpoint(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name'     => 'Student',
                'email'    => 'student@taalom.com',
                'password' => 'Student@12345',
                'role'     => 'student',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_student_cannot_create_users(): void
    {
        $this->actingAs($this->student(), 'sanctum')
            ->postJson('/api/admin/users', [
                'name'     => 'Teacher',
                'email'    => 'teacher@taalom.com',
                'password' => 'Teacher@12345',
                'role'     => 'teacher',
            ])
            ->assertForbidden();
    }

    public function test_store_requires_unique_email(): void
    {
        $admin    = $this->admin();
        $existing = $this->teacher();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', [
                'name'     => 'Duplicate',
                'email'    => $existing->email,
                'password' => 'Teacher@12345',
                'role'     => 'teacher',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ---- PUT /api/admin/users/{user} ----

    public function test_admin_can_update_user(): void
    {
        Event::fake([UserUpdated::class]);

        $admin   = $this->admin();
        $teacher = $this->teacher();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/users/{$teacher->id}", [
                'name' => 'Updated Teacher Name',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Teacher Name');

        Event::assertDispatched(UserUpdated::class);
    }

    // ---- DELETE /api/admin/users/{user} ----

    public function test_admin_can_soft_delete_user(): void
    {
        Storage::fake('public');
        Event::fake([UserDeleted::class]);

        $admin   = $this->admin();
        $teacher = $this->teacher();
        $photoPath = UploadedFile::fake()->image('teacher.jpg')->store('photos/users', 'public');
        $teacher->update(['photo_path' => $photoPath]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$teacher->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertSoftDeleted('users', ['id' => $teacher->id]);
        $this->assertNull($teacher->fresh()->photo_path);
        Storage::disk('public')->assertMissing($photoPath);
        Event::assertDispatched(UserDeleted::class);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$admin->id}")
            ->assertForbidden();
    }

    // ---- POST /api/admin/users/{id}/restore ----

    public function test_admin_can_restore_soft_deleted_user(): void
    {
        Event::fake([UserRestored::class]);

        $admin   = $this->admin();
        $teacher = $this->teacher();
        $teacher->delete();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$teacher->id}/restore")
            ->assertOk()
            ->assertJsonPath('message', 'User restored successfully.');

        $this->assertNotSoftDeleted('users', ['id' => $teacher->id]);
        Event::assertDispatched(UserRestored::class);
    }

    // ---- PUT /api/admin/users/{user}/status ----

    public function test_admin_can_suspend_user(): void
    {
        Event::fake([UserStatusChanged::class]);

        $admin   = $this->admin();
        $teacher = $this->teacher();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/users/{$teacher->id}/status", ['status' => 'suspended'])
            ->assertOk()
            ->assertJsonPath('message', 'User status updated successfully.');

        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'status' => 'suspended']);
        Event::assertDispatched(UserStatusChanged::class);
    }

    public function test_admin_can_activate_user(): void
    {
        $admin   = $this->admin();
        $teacher = $this->teacher();
        $teacher->update(['status' => UserStatus::SUSPENDED]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/users/{$teacher->id}/status", ['status' => 'active'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $teacher->id, 'status' => 'active']);
    }

    public function test_admin_cannot_change_their_own_status(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/users/{$admin->id}/status", ['status' => 'inactive'])
            ->assertForbidden();
    }

    public function test_status_change_rejects_pending_value(): void
    {
        $admin   = $this->admin();
        $teacher = $this->teacher();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/users/{$teacher->id}/status", ['status' => 'pending'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ---- GET /api/admin/users/{user} ----

    public function test_admin_can_view_single_user(): void
    {
        $admin   = $this->admin();
        $teacher = $this->teacher();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/users/{$teacher->id}")
            ->assertOk()
            ->assertJsonPath('user.id', $teacher->id);
    }
}
