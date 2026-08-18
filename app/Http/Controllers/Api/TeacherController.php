<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $teachers = User::where('role', UserRole::TEACHER)
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->paginate($request->input('per_page', 15));

        return UserResource::collection($teachers);
    }

    public function show(User $teacher)
    {
        $this->authorizeTeacherAccess(request()->user(), $teacher);

        return new UserResource($teacher);
    }

    public function courses(User $teacher)
    {
        $this->authorizeTeacherAccess(request()->user(), $teacher);

        $courses = Course::where('teacher_id', $teacher->id)
            ->with(['teacher', 'category'])
            ->withCount(['lessons', 'enrollments'])
            ->get();

        return CourseResource::collection($courses);
    }

    public function dashboard(Request $request)
    {
        $teacher = $request->user();

        $courseIds = Course::where('teacher_id', $teacher->id)->pluck('id');

        $totalCourses = $courseIds->count();

        $activeStudentsCount = Enrollment::whereIn('course_id', $courseIds)
            ->where('status', 'active')
            ->distinct('student_id')
            ->count('student_id');

        $totalEnrollments = Enrollment::whereIn('course_id', $courseIds)->count();

        return response()->json([
            'data' => [
                'total_courses' => $totalCourses,
                'active_students' => $activeStudentsCount,
                'total_enrollments' => $totalEnrollments,
            ]
        ]);
    }

    public function analytics(Request $request)
    {
        $teacher = $request->user();

        $courses = Course::where('teacher_id', $teacher->id)
            ->withCount('enrollments')
            ->get();

        $coursePerformance = $courses->map(function ($course) {
            return [
                'course_id' => $course->id,
                'title' => $course->title,
                'enrollments_count' => $course->enrollments_count,
            ];
        });

        // Mock monthly trend data for the frontend graphs
        $monthlyStudents = [
            ['month' => 'Jan', 'count' => 12],
            ['month' => 'Feb', 'count' => 25],
            ['month' => 'Mar', 'count' => 45],
            ['month' => 'Apr', 'count' => 60],
            ['month' => 'May', 'count' => 85],
            ['month' => 'Jun', 'count' => 120],
        ];

        return response()->json([
            'data' => [
                'course_performance' => $coursePerformance,
                'monthly_students' => $monthlyStudents,
            ]
        ]);
    }

    private function authorizeTeacherAccess(?User $requestUser, User $teacher): void
    {
        abort_unless($teacher->isTeacher(), 404, 'Teacher not found.');

        if (! $requestUser) {
            abort(403, 'Forbidden.');
        }

        abort_unless(
            $requestUser->isAdmin() || $requestUser->id === $teacher->id,
            403,
            'Forbidden.'
        );
    }

    public function grades(Request $request)
    {
        $teacher = $request->user();

        $courseIds = Course::where('teacher_id', $teacher->id)->pluck('id');

        if ($request->filled('course_id')) {
            $filterCourse = (int) $request->input('course_id');
            if (! $courseIds->contains($filterCourse)) {
                return response()->json(['data' => []]);
            }
            $courseIds = collect([$filterCourse]);
        }

        $studentIds = Enrollment::whereIn('course_id', $courseIds)
            ->distinct()
            ->pluck('student_id');

        if ($courseIds->isEmpty() || $studentIds->isEmpty()) {
            return response()->json([
                'data' => $this->emptyGradesPayload(),
            ]);
        }

        $quizSubs = QuizSubmission::query()
            ->whereIn('user_id', $studentIds)
            ->whereIn('status', ['passed', 'failed'])
            ->whereHas('quiz', function ($query) use ($courseIds) {
                $query->whereIn('course_id', $courseIds)
                    ->orWhereHas('section', function ($sectionQuery) use ($courseIds) {
                        $sectionQuery->whereIn('course_id', $courseIds);
                    });
            })
            ->with(['user:id,name,photo_path', 'quiz:id,course_id,course_section_id,questions'])
            ->get();

        $examAttempts = ExamAttempt::query()
            ->whereIn('user_id', $studentIds)
            ->whereIn('status', ['passed', 'failed'])
            ->whereHas('exam', function ($query) use ($courseIds) {
                $query->whereIn('course_id', $courseIds);
            })
            ->with(['user:id,name,photo_path', 'exam:id,course_id'])
            ->get();

        $entries = collect();

        foreach ($quizSubs as $submission) {
            $totalScore = (int) ($submission->total_score ?? 0);
            if ($totalScore <= 0) {
                $totalScore = $this->quizTotalScore($submission->quiz?->questions ?? []);
            }

            if ($totalScore <= 0) {
                continue;
            }

            $entries->push($this->normalizeGradeEntry(
                studentId: (int) $submission->user_id,
                name: $submission->user?->name,
                photoPath: $submission->user?->photo_path,
                percent: round(((float) $submission->score / $totalScore) * 100, 2),
                status: (string) $submission->status
            ));
        }

        foreach ($examAttempts as $attempt) {
            $entries->push($this->normalizeGradeEntry(
                studentId: (int) $attempt->user_id,
                name: $attempt->user?->name,
                photoPath: $attempt->user?->photo_path,
                percent: round((float) $attempt->score, 2),
                status: (string) $attempt->status
            ));
        }

        if ($entries->isEmpty()) {
            return response()->json([
                'data' => $this->emptyGradesPayload(),
            ]);
        }

        $totalGraded = $entries->count();
        $totalPassed = $entries->where('status', 'passed')->count();
        $totalFailed = $entries->where('status', 'failed')->count();
        $overallAverage = round($entries->avg('average_percent') ?? 0, 1);
        $passRate = $totalGraded > 0 ? round(($totalPassed / $totalGraded) * 100, 1) : 0.0;
        $failRate = $totalGraded > 0 ? round(($totalFailed / $totalGraded) * 100, 1) : 0.0;

        $ranking = $entries
            ->groupBy('student_id')
            ->map(function (Collection $studentEntries) {
                $first = $studentEntries->first();
                $gradedCount = $studentEntries->count();
                $passedCount = $studentEntries->where('status', 'passed')->count();
                $failedCount = $studentEntries->where('status', 'failed')->count();

                return [
                    'student_id' => $first['student_id'],
                    'name' => $first['name'],
                    'photo_url' => $first['photo_url'],
                    'average_percent' => round($studentEntries->avg('average_percent') ?? 0, 1),
                    'graded_count' => $gradedCount,
                    'passed_count' => $passedCount,
                    'failed_count' => $failedCount,
                ];
            })
            ->sort(function (array $left, array $right) {
                if ($left['average_percent'] !== $right['average_percent']) {
                    return $right['average_percent'] <=> $left['average_percent'];
                }

                if ($left['graded_count'] !== $right['graded_count']) {
                    return $right['graded_count'] <=> $left['graded_count'];
                }

                return $left['student_id'] <=> $right['student_id'];
            })
            ->values();

        return response()->json([
            'data' => [
                'overall_average' => $overallAverage,
                'total_graded' => $totalGraded,
                'total_passed' => $totalPassed,
                'total_failed' => $totalFailed,
                'pass_rate' => $passRate,
                'fail_rate' => $failRate,
                'ranking' => $ranking,
            ],
        ]);
    }

    private function normalizeGradeEntry(int $studentId, ?string $name, ?string $photoPath, float $percent, string $status): array
    {
        return [
            'student_id' => $studentId,
            'name' => $name,
            'photo_url' => $photoPath ? asset('storage/' . $photoPath) : null,
            'average_percent' => $percent,
            'status' => $status,
        ];
    }

    private function quizTotalScore(array $questions): int
    {
        return array_sum(array_map(
            static fn (array $question) => (int) ($question['points'] ?? 1),
            $questions
        ));
    }

    private function emptyGradesPayload(): array
    {
        return [
            'overall_average' => 0,
            'total_graded' => 0,
            'total_passed' => 0,
            'total_failed' => 0,
            'pass_rate' => 0,
            'fail_rate' => 0,
            'ranking' => [],
        ];
    }
}
