<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Models\Enrollment;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    public function show(Certificate $certificate)
    {
        $this->authorizeCertificateAccess(request()->user(), $certificate);

        return new CertificateResource($certificate->load(['course', 'student']));
    }

    public function issue(Request $request, Course $course)
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
        ]);

        $student = User::findOrFail($request->student_id);

        if (!$student->isStudent()) {
            return response()->json(['message' => 'User is not a student.'], 400);
        }

        $certificate = Certificate::firstOrCreate([
            'course_id' => $course->id,
            'student_id' => $student->id,
        ], [
            'certificate_code' => 'CERT-' . strtoupper(Str::random(8)),
            'issued_at' => now(),
        ]);

        return response()->json([
            'message' => 'Certificate issued successfully.',
            'certificate' => new CertificateResource($certificate->load(['course', 'student'])),
        ], 201);
    }

    public function byStudent(User $student)
    {
        $this->authorizeStudentAccess(request()->user(), $student);

        $certificates = Certificate::where('student_id', $student->id)
            ->with(['course', 'student'])
            ->get();

        return CertificateResource::collection($certificates);
    }

    private function authorizeCertificateAccess(?User $requestUser, Certificate $certificate): void
    {
        $certificate->loadMissing(['course', 'student']);
        abort_unless($certificate->student?->isStudent(), 404, 'Student not found.');

        if (! $requestUser) {
            abort(403, 'Forbidden.');
        }

        if ($requestUser->isAdmin() || (int) $requestUser->id === (int) $certificate->student_id) {
            return;
        }

        abort_unless(
            $requestUser->isTeacher() && (int) $certificate->course?->teacher_id === (int) $requestUser->id,
            403,
            'Forbidden.'
        );
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
