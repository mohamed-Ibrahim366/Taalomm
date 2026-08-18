<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Http\Resources\CourseResource;
use App\Http\Resources\UserResource;
use App\Models\Course;
use App\Models\Group;
use App\Models\User;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $students = User::where('role', UserRole::STUDENT)
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->paginate($request->input('per_page', 15));

        return UserResource::collection($students);
    }

    public function show(User $student)
    {
        $this->authorizeStudentAccess(request()->user(), $student);

        return new UserResource($student);
    }

    public function update(Request $request, User $student)
    {
        $this->authorizeStudentAccess($request->user(), $student);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $student->id,
            'phone' => 'nullable|string|max:20',
            'governorate' => 'nullable|string|max:255',
            'grades' => 'nullable|string|max:255',
        ]);

        $student->update($validated);
        return new UserResource($student);
    }

    public function enroll(Request $request, User $student)
    {
        if (!$student->isStudent()) {
            abort(400, 'User is not a student.');
        }

        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $enrollment = Enrollment::firstOrCreate([
            'student_id' => $student->id,
            'course_id' => $request->course_id,
        ], [
            'status' => 'active',
            'progress_percent' => 0,
        ]);

        return response()->json([
            'message' => 'Enrolled successfully.',
            'enrollment' => $enrollment,
        ], 201);
    }

    public function courses(User $student)
    {
        $this->authorizeStudentAccess(request()->user(), $student);

        $enrollments = Enrollment::where('student_id', $student->id)
            ->with(['course.teacher', 'course.category'])
            ->get();

        $requestUser = request()->user();
        if ($requestUser?->isTeacher() && ! $requestUser->isAdmin()) {
            $enrollments = $enrollments->filter(function (Enrollment $enrollment) use ($requestUser) {
                return (int) ($enrollment->course?->teacher_id) === (int) $requestUser->id;
            })->values();
        }

        $courses = $enrollments->map(function ($enrollment) {
            return $enrollment->course;
        });

        return CourseResource::collection($courses);
    }

    public function groups(User $student)
    {
        $requestUser = request()->user();

        $this->authorizeStudentAccess($requestUser, $student);

        return GroupResource::collection($this->groupsForStudent($student, $requestUser));
    }

    public function myGroups(Request $request)
    {
        $student = $request->user();

        if (!$student || !$student->isStudent()) {
            abort(403, 'Only students can view their groups.');
        }

        return GroupResource::collection($this->groupsForStudent($student));
    }

    public function progress(User $student, $courseId)
    {
        $this->authorizeStudentAccess(request()->user(), $student);

        $requestUser = request()->user();
        if ($requestUser?->isTeacher() && ! $requestUser->isAdmin()) {
            $ownsCourse = Course::where('id', $courseId)
                ->where('teacher_id', $requestUser->id)
                ->exists();

            abort_unless($ownsCourse, 403, 'You can only view progress for your own students.');
        }

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $courseId)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'course_id' => $enrollment->course_id,
                'student_id' => $enrollment->student_id,
                'status' => $enrollment->status,
                'progress_percent' => (float) $enrollment->progress_percent,
                'updated_at' => $enrollment->updated_at,
            ]
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Group>
     */
    private function groupsForStudent(User $student, ?User $requestUser = null)
    {
        return Group::query()
            ->whereHas('students', function ($query) use ($student) {
                $query->where('users.id', $student->id);
            })
            ->when($requestUser && $requestUser->isTeacher() && ! $requestUser->isAdmin(), function ($query) use ($requestUser) {
                $query->where('teacher_id', $requestUser->id);
            })
            ->with(['teacher', 'course', 'sessions'])
            ->latest()
            ->get();
    }

    private function authorizeStudentAccess(?User $requestUser, User $student): void
    {
        abort_unless($student->isStudent(), 404, 'Student not found.');

        if (! $requestUser) {
            abort(403, 'Forbidden.');
        }

        if ($requestUser->isAdmin() || $requestUser->id === $student->id) {
            return;
        }

        abort_unless(
            $requestUser->isTeacher() && $this->teacherHasStudentAccess($requestUser, $student),
            403,
            'Forbidden.'
        );
    }

    private function teacherHasStudentAccess(User $teacher, User $student): bool
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->whereHas('course', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })
            ->exists()
            || Group::query()
                ->where('teacher_id', $teacher->id)
                ->whereHas('students', function ($query) use ($student) {
                    $query->where('users.id', $student->id);
                })
                ->exists();
    }
}
