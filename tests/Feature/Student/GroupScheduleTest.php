<?php

namespace Tests\Feature\Student;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        return User::factory()->create([
            'role' => UserRole::STUDENT,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function teacher(): User
    {
        return User::factory()->create([
            'role' => UserRole::TEACHER,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    public function test_student_can_view_own_group_schedule(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();
        $category = Category::factory()->create();
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'category_id' => $category->id,
            'title' => 'تعلم اللغه العربيه',
            'slug' => 'taalem-al-lugha-al-arabiya',
        ]);

        $group = Group::create([
            'name' => 'مجموعة الأحد والثلاثاء',
            'description' => 'Test group',
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
        ]);

        $group->students()->attach($student->id);
        $group->sessions()->createMany([
            ['day' => 'sunday', 'start_time' => '18:00', 'end_time' => '19:30'],
            ['day' => 'tuesday', 'start_time' => '18:00', 'end_time' => '19:30'],
        ]);

        $otherGroup = Group::create([
            'name' => 'مجموعة أخرى',
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/students/{$student->id}/groups")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $group->id)
            ->assertJsonPath('data.0.name', 'مجموعة الأحد والثلاثاء')
            ->assertJsonPath('data.0.teacher.id', $teacher->id)
            ->assertJsonPath('data.0.teacher.name', $teacher->name)
            ->assertJsonPath('data.0.course.id', $course->id)
            ->assertJsonPath('data.0.course.title', 'تعلم اللغه العربيه')
            ->assertJsonPath('data.0.schedule.0.day', 'sunday')
            ->assertJsonPath('data.0.schedule.1.day', 'tuesday');
    }

    public function test_student_can_view_own_group_schedule_via_me_route(): void
    {
        $student = $this->student();
        $teacher = $this->teacher();
        $category = Category::factory()->create();
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
            'category_id' => $category->id,
            'title' => 'تعلم اللغه العربيه',
            'slug' => 'taalem-al-lugha-al-arabiya',
        ]);

        $group = Group::create([
            'name' => 'مجموعة الأحد والثلاثاء',
            'description' => 'Test group',
            'teacher_id' => $teacher->id,
            'course_id' => $course->id,
        ]);

        $group->students()->attach($student->id);
        $group->sessions()->createMany([
            ['day' => 'sunday', 'start_time' => '18:00', 'end_time' => '19:30'],
            ['day' => 'tuesday', 'start_time' => '18:00', 'end_time' => '19:30'],
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/students/me/groups')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $group->id)
            ->assertJsonPath('data.0.schedule.0.start_time', '18:00')
            ->assertJsonPath('data.0.schedule.1.end_time', '19:30');
    }

    public function test_student_cannot_view_another_students_groups(): void
    {
        $student = $this->student();
        $otherStudent = $this->student();

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/students/{$otherStudent->id}/groups")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_view_student_groups(): void
    {
        $student = $this->student();

        $this->getJson("/api/students/{$student->id}/groups")
            ->assertUnauthorized();
    }
}
