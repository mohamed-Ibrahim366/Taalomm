<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function show(Assignment $assignment)
    {
        $this->authorizeAssignmentView(request()->user(), $assignment);

        return new AssignmentResource($assignment);
    }

    public function store(Request $request)
    {
        $request->validate([
            'course_section_id' => 'required|exists:course_sections,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'due_date' => 'required|date',
            'max_score' => 'nullable|integer|min:1',
            'file' => 'nullable|file|max:20480', // max 20MB
        ]);

        $this->authorizeCourseSectionAccess($request->user(), (int) $request->course_section_id);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('assignments/prompts', 'public');
        }

        $assignment = Assignment::create([
            'course_section_id' => $request->course_section_id,
            'title' => $request->title,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'max_score' => $request->max_score ?? 100,
            'file_path' => $filePath,
        ]);

        return new AssignmentResource($assignment);
    }

    public function update(Request $request, Assignment $assignment)
    {
        $this->authorizeAssignmentManagement($request->user(), $assignment);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'max_score' => 'nullable|integer|min:1',
            'file' => 'nullable|file|max:20480',
        ]);

        $data = $request->only(['title', 'description', 'due_date', 'max_score']);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('assignments/prompts', 'public');
        }

        $assignment->update($data);

        return new AssignmentResource($assignment);
    }

    public function destroy(Assignment $assignment)
    {
        $this->authorizeAssignmentManagement(request()->user(), $assignment);

        $assignment->delete();
        return response()->json(['message' => 'Assignment deleted successfully.']);
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $this->authorizeAssignmentView($request->user(), $assignment);

        $request->validate([
            'file' => 'nullable|file|max:20480',
            'text_response' => 'nullable|string',
        ]);

        if (!$request->hasFile('file') && !$request->text_response) {
            return response()->json(['message' => 'Submission must contain a file or a text response.'], 400);
        }

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('assignments/submissions', 'public');
        }

        $submission = AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $request->user()->id,
            'file_path' => $filePath,
            'text_response' => $request->text_response,
        ]);

        return response()->json([
            'message' => 'Assignment submitted successfully.',
            'submission' => $submission,
        ], 201);
    }

    public function submissions(Assignment $assignment)
    {
        $this->authorizeAssignmentManagement(request()->user(), $assignment);

        $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)->with('student')->get();
        return response()->json(['data' => $submissions]);
    }

    public function grade(Request $request, AssignmentSubmission $submission)
    {
        $submission->loadMissing('assignment.section.course');
        $this->authorizeAssignmentManagement($request->user(), $submission->assignment);

        $assignment = $submission->assignment;
        $maxScore = $assignment->max_score ?? 100;

        $request->validate([
            'score' => "required|integer|min:0|max:{$maxScore}",
            'feedback' => 'nullable|string',
        ]);

        $submission->update([
            'score' => $request->score,
            'feedback' => $request->feedback,
            'graded_at' => now(),
            'graded_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Submission graded successfully.',
            'submission' => $submission,
        ]);
    }

    private function authorizeAssignmentManagement(?User $user, Assignment $assignment): void
    {
        $assignment->loadMissing('section.course');
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && (int) $assignment->section?->course?->teacher_id === (int) $user->id,
            403,
            'Forbidden.'
        );
    }

    private function authorizeAssignmentView(?User $user, Assignment $assignment): void
    {
        $assignment->loadMissing('section.course');
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin() || (int) $assignment->section?->course?->teacher_id === (int) $user->id) {
            return;
        }

        abort_unless(
            $user->isStudent()
            && Enrollment::query()
                ->where('student_id', $user->id)
                ->where('course_id', $assignment->section?->course_id)
                ->exists(),
            403,
            'Forbidden.'
        );
    }

    private function authorizeCourseSectionAccess(?User $user, int $courseSectionId): void
    {
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && \App\Models\CourseSection::query()
                ->whereKey($courseSectionId)
                ->whereHas('course', function ($query) use ($user) {
                    $query->where('teacher_id', $user->id);
                })
                ->exists(),
            403,
            'Forbidden.'
        );
    }
}
