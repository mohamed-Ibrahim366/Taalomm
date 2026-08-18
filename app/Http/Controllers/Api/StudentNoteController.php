<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\StudentNote;
use App\Models\User;
use Illuminate\Http\Request;

class StudentNoteController extends Controller
{
    public function byStudent(User $student)
    {
        $this->authorizeStudentAccess(request()->user(), $student);

        $notes = StudentNote::where('student_id', $student->id)
            ->with('teacher:id,name')
            ->latest()
            ->get();

        return response()->json(['data' => $notes]);
    }

    public function store(Request $request, User $student)
    {
        $this->authorizeStudentAccess($request->user(), $student);

        $request->validate([
            'note' => 'required|string',
        ]);

        $note = StudentNote::create([
            'student_id' => $student->id,
            'teacher_id' => $request->user()->id,
            'note' => $request->note,
        ]);

        return response()->json([
            'message' => 'Note added successfully.',
            'note' => $note,
        ], 201);
    }

    public function destroy(StudentNote $studentNote)
    {
        $user = request()->user();

        abort_unless(
            $user && (
                $user->isAdmin()
                || (int) $studentNote->teacher_id === (int) $user->id
            ),
            403,
            'Forbidden.'
        );

        $studentNote->delete();
        return response()->json(['message' => 'Note deleted successfully.']);
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
}
