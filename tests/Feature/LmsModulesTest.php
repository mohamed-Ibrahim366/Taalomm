<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LmsModulesTest extends TestCase
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

    // ---- Quiz Tests ----

    public function test_student_can_submit_quiz_and_auto_graded(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $quiz = Quiz::create([
            'course_section_id' => $section->id,
            'title' => 'Test Quiz',
            'passing_score' => 60,
            'questions' => [
                ['text' => 'Q1', 'correct_option' => 1],
                ['text' => 'Q2', 'correct_option' => 2],
            ],
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/quizzes/{$quiz->id}/submit", [
                'answers' => [0 => 1, 1 => 2], // Both correct
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.score', 2)
            ->assertJsonPath('data.totalScore', 2)
            ->assertJsonPath('data.status', 'passed');

        $this->assertDatabaseHas('quiz_submissions', [
            'quiz_id' => $quiz->id,
            'user_id' => $student->id,
            'score' => 2,
            'status' => 'passed',
        ]);
    }

    public function test_student_can_fetch_quiz_progress_and_best_result(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $quiz = Quiz::create([
            'course_section_id' => $section->id,
            'title' => 'Retry Quiz',
            'passing_score' => 60,
            'max_attempts' => 2,
            'questions' => [
                ['text' => 'Q1', 'correct_option' => 1],
            ],
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/quizzes/{$quiz->id}/submit", [
                'answers' => [0 => 0],
            ])
            ->assertStatus(201);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/quizzes/{$quiz->id}/submit", [
                'answers' => [0 => 1],
            ])
            ->assertStatus(201);

        $this->actingAs($student, 'sanctum')
            ->getJson("/api/quizzes/{$quiz->id}/progress")
            ->assertOk()
            ->assertJsonPath('data.alreadyAttempted', true)
            ->assertJsonPath('data.attemptsUsed', 2)
            ->assertJsonPath('data.remainingAttempts', 0)
            ->assertJsonPath('data.bestResult.score', 100)
            ->assertJsonPath('data.bestResult.attemptNumber', 2)
            ->assertJsonPath('data.latestAttempt.score', 100)
            ->assertJsonPath('data.bestResult.rawScore', 1);
    }

    public function test_teacher_grades_endpoint_returns_aggregated_statistics(): void
    {
        $teacher = $this->teacher();
        $student1 = $this->student();
        $student2 = $this->student();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        Enrollment::create([
            'student_id' => $student1->id,
            'course_id' => $course->id,
        ]);

        Enrollment::create([
            'student_id' => $student2->id,
            'course_id' => $course->id,
        ]);

        $quiz = Quiz::create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'title' => 'Grades Quiz',
            'passing_score' => 60,
            'questions' => [
                ['text' => 'Q1', 'points' => 10, 'correct_option' => 1],
            ],
        ]);

        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => 'Grades Exam',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'duration_minutes' => 60,
            'passing_score' => 50,
        ]);

        QuizSubmission::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student1->id,
            'attempt_number' => 1,
            'answers' => [1],
            'score' => 8,
            'total_score' => 10,
            'status' => 'passed',
            'submitted_at' => now(),
        ]);

        QuizSubmission::create([
            'quiz_id' => $quiz->id,
            'user_id' => $student2->id,
            'attempt_number' => 1,
            'answers' => [0],
            'score' => 4,
            'total_score' => 10,
            'status' => 'failed',
            'submitted_at' => now(),
        ]);

        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student1->id,
            'answers' => [],
            'score' => 90,
            'status' => 'passed',
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
        ]);

        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student2->id,
            'answers' => [],
            'score' => 40,
            'status' => 'failed',
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now(),
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/teachers/grades')
            ->assertOk()
            ->assertJsonPath('data.overall_average', 62.5)
            ->assertJsonPath('data.total_graded', 4)
            ->assertJsonPath('data.total_passed', 2)
            ->assertJsonPath('data.total_failed', 2)
            ->assertJsonPath('data.pass_rate', 50)
            ->assertJsonPath('data.fail_rate', 50)
            ->assertJsonPath('data.ranking.0.student_id', $student1->id)
            ->assertJsonPath('data.ranking.0.average_percent', 85)
            ->assertJsonPath('data.ranking.0.graded_count', 2)
            ->assertJsonPath('data.ranking.0.passed_count', 2)
            ->assertJsonPath('data.ranking.0.failed_count', 0)
            ->assertJsonPath('data.ranking.1.student_id', $student2->id)
            ->assertJsonPath('data.ranking.1.average_percent', 40)
            ->assertJsonPath('data.ranking.1.graded_count', 2)
            ->assertJsonPath('data.ranking.1.passed_count', 0)
            ->assertJsonPath('data.ranking.1.failed_count', 2);
    }

    public function test_course_owner_can_access_their_meetings(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        Meeting::create([
            'course_id' => $course->id,
            'teacher_id' => $teacher->id,
            'title' => 'Review Session',
            'room_name' => 'taalom-test-room',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/courses/{$course->slug}/meetings")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ---- Exam Tests ----

    public function test_student_cannot_attempt_exam_outside_active_window(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => 'Scheduled Exam',
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(2),
            'duration_minutes' => 60,
            'passing_score' => 50,
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/exams/{$exam->id}/attempt", [
                'answers' => [],
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'This exam is not currently active.');
    }

    public function test_student_cannot_attempt_exam_twice(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        Enrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => 'Scheduled Exam',
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(1),
            'duration_minutes' => 60,
            'passing_score' => 50,
        ]);

        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'started_at' => now(),
        ]);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/exams/{$exam->id}/attempt", [
                'answers' => [],
            ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'You have already attempted this exam.')
            ->assertJsonPath('progress.alreadyAttempted', true)
            ->assertJsonPath('progress.remainingAttempts', 0);
    }

    // ---- Assignment Tests ----

    public function test_student_can_submit_assignment_and_teacher_can_grade(): void
    {
        $teacher = $this->teacher();
        $student = $this->student();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $section = CourseSection::factory()->create(['course_id' => $course->id]);

        $assignment = Assignment::create([
            'course_section_id' => $section->id,
            'title' => 'Homework 1',
            'description' => 'Write a paragraph',
            'due_date' => now()->addDays(5),
            'max_score' => 100,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submit", [
                'text_response' => 'Hello this is my assignment.',
            ])
            ->assertStatus(201);

        $submissionId = $response->json('submission.id');

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/assignments/submissions/{$submissionId}/grade", [
                'score' => 85,
                'feedback' => 'Good job!',
            ])
            ->assertOk();

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submissionId,
            'score' => 85,
            'feedback' => 'Good job!',
        ]);
    }

    // ---- Payment Tests ----

    public function test_student_can_request_payment_and_admin_can_approve(): void
    {
        $student = $this->student();
        $admin = $this->admin();

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/payments", [
                'amount' => 500,
                'payment_method' => 'Cash',
            ])
            ->assertStatus(201);

        $paymentId = $response->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/payments/{$paymentId}/approve")
            ->assertOk();

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
    }

    // ---- Attendance Batch Tests ----

    public function test_teacher_can_batch_mark_attendance_for_group(): void
    {
        $teacher = $this->teacher();
        $student1 = $this->student();
        $student2 = $this->student();

        $group = Group::create([
            'name' => 'Grade 10 - A',
            'teacher_id' => $teacher->id,
        ]);

        $group->students()->attach([$student1->id, $student2->id]);

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/groups/{$group->id}/attendance", [
                'date' => '2026-08-07',
                'attendance' => [
                    ['student_id' => $student1->id, 'status' => 'present'],
                    ['student_id' => $student2->id, 'status' => 'absent'],
                ]
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('attendances', [
            'group_id' => $group->id,
            'student_id' => $student1->id,
            'status' => 'present',
        ]);

        $this->assertDatabaseHas('attendances', [
            'group_id' => $group->id,
            'student_id' => $student2->id,
            'status' => 'absent',
        ]);
    }
}
