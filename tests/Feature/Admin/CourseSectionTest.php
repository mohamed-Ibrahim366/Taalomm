<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseSectionTest extends TestCase
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

    // ---- Public Browsing Tests ----

    public function test_guest_can_list_published_courses(): void
    {
        $category = Category::factory()->create();
        $published = Course::factory()->create([
            'category_id' => $category->id,
            'is_published' => true,
        ]);
        $draft = Course::factory()->create([
            'category_id' => $category->id,
            'is_published' => false,
        ]);

        $response = $this->getJson('/api/courses')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'slug', 'description', 'price', 'is_published']]
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($published->title, $response->json('data.0.title'));
    }

    public function test_guest_can_view_published_course_and_sections(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $section = CourseSection::factory()->create([
            'course_id' => $course->id,
            'is_published' => true,
        ]);

        $this->getJson("/api/courses/{$course->slug}")
            ->assertOk()
            ->assertJsonPath('data.title', $course->title)
            ->assertJsonStructure([
                'data' => ['sections' => [['id', 'title', 'order']]]
            ]);

        $this->getJson("/api/courses/{$course->slug}/sections")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_view_draft_course(): void
    {
        $course = Course::factory()->create(['is_published' => false]);

        $this->getJson("/api/courses/{$course->slug}")
            ->assertNotFound();
    }

    // ---- Course Section CRUD Tests ----

    public function test_teacher_can_create_section_for_own_course(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/teacher/courses/{$course->slug}/sections", [
                'title' => 'Introduction to Laravel',
                'description' => 'Laravel basics',
                'is_published' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('course_sections', [
            'course_id' => $course->id,
            'title' => 'Introduction to Laravel',
        ]);
    }

    public function test_teacher_cannot_create_section_for_other_teacher_course(): void
    {
        $teacher1 = $this->teacher();
        $teacher2 = $this->teacher();
        $course = Course::factory()->create(['teacher_id' => $teacher2->id]);

        $this->actingAs($teacher1, 'sanctum')
            ->postJson("/api/teacher/courses/{$course->slug}/sections", [
                'title' => 'Introduction to Laravel',
                'description' => 'Laravel basics',
                'is_published' => true,
            ])
            ->assertForbidden();
    }

    public function test_teacher_can_update_own_section(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $this->actingAs($teacher, 'sanctum')
            ->putJson("/api/sections/{$section->id}", [
                'title' => 'Updated Title',
            ])
            ->assertOk();

        $this->assertEquals('Updated Title', $section->fresh()->title);
    }

    public function test_teacher_can_delete_own_section(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $this->actingAs($teacher, 'sanctum')
            ->deleteJson("/api/sections/{$section->id}")
            ->assertOk();

        $this->assertSoftDeleted('course_sections', ['id' => $section->id]);
    }

    public function test_teacher_can_reorder_sections(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $section1 = CourseSection::factory()->create(['course_id' => $course->id, 'order' => 1]);
        $section2 = CourseSection::factory()->create(['course_id' => $course->id, 'order' => 2]);

        $this->actingAs($teacher, 'sanctum')
            ->putJson("/api/sections/reorder", [
                'sections' => [
                    ['id' => $section1->id, 'order' => 2],
                    ['id' => $section2->id, 'order' => 1],
                ]
            ])
            ->assertOk();

        $this->assertEquals(2, $section1->fresh()->order);
        $this->assertEquals(1, $section2->fresh()->order);
    }
}
