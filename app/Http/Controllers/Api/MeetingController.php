<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeetingResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function index(Request $request, Course $course)
    {
        $this->authorizeCourseAccess($request->user(), $course);

        $meetings = Meeting::query()
            ->where('course_id', $course->id)
            ->with(['course.teacher', 'teacher'])
            ->orderBy('scheduled_at')
            ->get();

        return MeetingResource::collection($meetings);
    }

    public function store(Request $request, Course $course)
    {
        $this->authorizeCourseAccess($request->user(), $course);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'scheduled_at' => 'required|date',
            'room_name' => 'required|string|max:255',
        ]);

        $meeting = Meeting::create([
            'course_id' => $course->id,
            'teacher_id' => $course->teacher_id,
            'title' => $validated['title'],
            'scheduled_at' => $validated['scheduled_at'],
            'room_name' => $validated['room_name'],
        ])->load(['course.teacher', 'teacher']);

        return (new MeetingResource($meeting))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Meeting $meeting)
    {
        $meeting->loadMissing('course');
        $this->authorizeCourseAccess($request->user(), $meeting->course);

        $meeting->delete();

        return response()->json([
            'message' => 'Meeting deleted successfully.',
        ]);
    }

    public function byStudent(Request $request, User $student)
    {
        abort_unless($student->isStudent(), 404, 'Student not found.');

        $this->authorizeStudentAccess($request->user(), $student);

        $courseIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->pluck('course_id');

        $meetings = Meeting::query()
            ->whereIn('course_id', $courseIds)
            ->with(['course.teacher', 'teacher'])
            ->orderBy('scheduled_at')
            ->get();

        return MeetingResource::collection($meetings);
    }

    private function authorizeCourseAccess(?User $user, Course $course): void
    {
        abort_unless(
            $user && (
                $user->isAdmin()
                || (int) $course->teacher_id === (int) $user->id
            ),
            403,
            'You are not allowed to manage meetings for this course.'
        );
    }

    private function authorizeStudentAccess(?User $user, User $student): void
    {
        if (! $user) {
            abort(403, 'Forbidden.');
        }

        if ($user->isAdmin() || $user->id === $student->id) {
            return;
        }

        abort_unless(
            $user->isTeacher() && Enrollment::query()
                ->where('student_id', $student->id)
                ->whereHas('course', function ($query) use ($user) {
                    $query->where('teacher_id', $user->id);
                })
                ->exists(),
            403,
            'Forbidden.'
        );
    }
}
