<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Resources\Admin\TeacherResource;
use App\Http\Resources\CourseResource;
use App\Http\Resources\UserResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    public function overview(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_teachers' => User::where('role', UserRole::TEACHER)->count(),
                'total_students' => User::where('role', UserRole::STUDENT)->count(),
                'total_courses' => Course::count(),
                'total_payments' => Payment::count(),
                'total_revenue' => (float) Payment::where('status', 'approved')->sum('amount'),
                'pending_payments' => Payment::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function teachers(Request $request): JsonResponse
    {
        $teachers = User::query()
            ->where('role', UserRole::TEACHER)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->withCount('courses')
            ->latest()
            ->paginate($request->input('per_page', 15));

        $this->attachTeacherStudentCounts($teachers->getCollection());

        return response()->json(
            TeacherResource::collection($teachers)->response()->getData(true)
        );
    }

    public function storeTeacher(StoreTeacherRequest $request): JsonResponse
    {
        $teacher = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => Hash::make($request->validated('password')),
            'role' => UserRole::TEACHER,
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'data' => new TeacherResource($teacher->loadCount('courses')),
        ], 201);
    }

    public function students(Request $request): JsonResponse
    {
        $students = User::query()
            ->where('role', UserRole::STUDENT)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json(
            UserResource::collection($students)->response()->getData(true)
        );
    }

    public function courses(Request $request): JsonResponse
    {
        $courses = Course::query()
            ->with(['teacher:id,name,photo_path', 'category:id,name'])
            ->withCount(['lessons', 'enrollments'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->category_id, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($request->teacher_id, fn ($query, $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($request->level, fn ($query, $level) => $query->where('level', $level))
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json(
            CourseResource::collection($courses)->response()->getData(true)
        );
    }

    private function attachTeacherStudentCounts(Collection $teachers): void
    {
        $teacherIds = $teachers->pluck('id');

        if ($teacherIds->isEmpty()) {
            return;
        }

        $studentCounts = Enrollment::query()
            ->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->whereIn('courses.teacher_id', $teacherIds)
            ->groupBy('courses.teacher_id')
            ->selectRaw('courses.teacher_id as teacher_id, COUNT(DISTINCT enrollments.student_id) as students_count')
            ->pluck('students_count', 'teacher_id');

        $teachers->transform(function (User $teacher) use ($studentCounts) {
            $teacher->setAttribute('students_count', (int) ($studentCounts[$teacher->id] ?? 0));

            return $teacher;
        });
    }
}
