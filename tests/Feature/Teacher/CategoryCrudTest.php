<?php

namespace Tests\Feature\Teacher;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User
    {
        return User::factory()->create([
            'role' => UserRole::TEACHER,
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

    public function test_teacher_can_create_category(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'الكيمياء',
                'description' => 'كورسات الكيمياء للمرحلة الثانوية',
                'icon' => 'chemistry-icon',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'الكيمياء')
            ->assertJsonPath('data.icon', 'chemistry-icon')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('categories', [
            'name' => 'الكيمياء',
            'icon' => 'chemistry-icon',
            'is_active' => true,
        ]);

        $this->assertDatabaseCount('categories', 1);
    }

    public function test_teacher_can_update_category(): void
    {
        $teacher = $this->teacher();
        $category = Category::factory()->create([
            'name' => 'الفيزياء',
            'icon' => 'physics-icon',
            'is_active' => true,
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->putJson("/api/categories/{$category->id}", [
                'name' => 'الفيزياء المتقدمة',
                'description' => 'شرح أعمق للفيزياء',
                'icon' => 'advanced-physics-icon',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'الفيزياء المتقدمة')
            ->assertJsonPath('data.icon', 'advanced-physics-icon')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'الفيزياء المتقدمة',
            'icon' => 'advanced-physics-icon',
            'is_active' => false,
        ]);
    }

    public function test_teacher_can_delete_category_without_courses(): void
    {
        $teacher = $this->teacher();
        $category = Category::factory()->create();

        $this->actingAs($teacher, 'sanctum')
            ->deleteJson("/api/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Category deleted successfully.');

        $this->assertSoftDeleted('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_teacher_cannot_delete_category_with_courses(): void
    {
        $teacher = $this->teacher();
        $category = Category::factory()->create();
        Course::factory()->create([
            'teacher_id' => $teacher->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->deleteJson("/api/categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_student_cannot_manage_categories(): void
    {
        $student = $this->student();

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/categories', [
                'name' => 'الكيمياء',
                'description' => 'كورسات الكيمياء للمرحلة الثانوية',
                'icon' => 'chemistry-icon',
                'is_active' => true,
            ])
            ->assertForbidden();
    }
}
