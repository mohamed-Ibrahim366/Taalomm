<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstallmentResource;
use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\User;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    public function byStudent(User $student)
    {
        $this->authorizeStudentAccess(request()->user(), $student);

        $installments = Installment::where('student_id', $student->id)
            ->with(['course'])
            ->get();

        return InstallmentResource::collection($installments);
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
