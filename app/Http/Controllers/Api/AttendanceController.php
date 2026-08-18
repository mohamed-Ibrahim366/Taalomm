<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request, Group $group)
    {
        $this->authorizeGroupAccess($request->user(), $group);

        $request->validate([
            'date' => 'nullable|date',
        ]);

        $query = Attendance::where('group_id', $group->id)->with('student');

        if ($request->date) {
            $query->whereDate('date', $request->date);
        }

        $attendances = $query->get();

        return AttendanceResource::collection($attendances);
    }

    public function store(Request $request, Group $group)
    {
        $this->authorizeGroupAccess($request->user(), $group);

        $request->validate([
            'date' => 'required|date',
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:users,id',
            'attendance.*.status' => 'required|in:present,absent,excused,late',
        ]);

        $date = $request->input('date');
        $records = [];

        foreach ($request->input('attendance') as $record) {
            $attendance = Attendance::updateOrCreate([
                'group_id' => $group->id,
                'student_id' => $record['student_id'],
                'date' => $date,
            ], [
                'status' => $record['status'],
            ]);

            $records[] = $attendance->load('student');
        }

        return response()->json([
            'message' => 'Attendance recorded successfully.',
            'data' => AttendanceResource::collection(collect($records)),
        ], 201);
    }

    public function byStudent(User $student)
    {
        $this->authorizeStudentAccess(request()->user(), $student);

        $attendances = Attendance::where('student_id', $student->id)
            ->with('group')
            ->latest('date')
            ->get();

        return response()->json(['data' => $attendances]);
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
            $requestUser->isTeacher() && Enrollment::query()
                ->where('student_id', $student->id)
                ->whereHas('course', function ($query) use ($requestUser) {
                    $query->where('teacher_id', $requestUser->id);
                })
                ->exists(),
            403,
            'Forbidden.'
        );
    }

    private function authorizeGroupAccess(?User $user, Group $group): void
    {
        $group->loadMissing('course');
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && (
                (int) $group->teacher_id === (int) $user->id
                || (int) $group->course?->teacher_id === (int) $user->id
            ),
            403,
            'Forbidden.'
        );
    }
}
