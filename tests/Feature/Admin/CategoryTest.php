<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function student(): User
    {
        return User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    // ---- Public Browsing Tests ----

    public function test_guest_can_list_active_categories(): void
    {
        Category::factory()->create(['name' => 'Active Math', 'is_active' => true]);
        Category::factory()->create(['name' => 'Inactive Physics', 'is_active' => false]);

        $response = $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'description', 'is_active']]
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Active Math', $response->json('data.0.name'));
    }

    public function test_guest_cannot_view_inactive_category(): void
    {
        $category = Category::factory()->create(['is_active' => false]);

        $this->getJson("/api/categories/{$category->id}")
            ->assertNotFound();
    }

    public function test_guest_can_view_active_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $this->getJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.name', $category->name);
    }

    // ---- Admin Category Management Tests ----

    public function test_admin_can_create_category(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/categories', [
                'name' => 'Chemistry',
                'description' => 'Chemistry classes',
                'is_active' => true,
                'icon' => UploadedFile::fake()->image('chem.png'),
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Chemistry');

        $this->assertDatabaseHas('categories', [
            'name' => 'Chemistry',
            'slug' => 'chemistry',
            'is_active' => true,
        ]);
    }

    public function test_student_cannot_create_category(): void
    {
        $student = $this->student();

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/admin/categories', [
                'name' => 'Chemistry',
                'description' => 'Chemistry classes',
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_category(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/categories/{$category->id}", [
                'name' => 'New Name',
                'description' => $category->description,
                'is_active' => $category->is_active,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_delete_category(): void
    {
        $admin = $this->admin();
        $category = Category::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertOk();

        $this->assertSoftDeleted('categories', [
            'id' => $category->id,
        ]);
    }
}
