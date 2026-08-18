<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExamController extends Controller
{
    private function formatAttemptSummary(ExamAttempt $attempt): array
    {
        return [
            'attemptId' => $attempt->id,
            'score' => (int) $attempt->score,
            'status' => $attempt->status,
            'startedAt' => $attempt->started_at?->toIso8601String(),
            'submittedAt' => $attempt->submitted_at?->toIso8601String() ?? $attempt->created_at?->toIso8601String(),
        ];
    }

    private function buildProgressPayload(Exam $exam, int $userId): array
    {
        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $userId)
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $latestAttempt = $attempts->last();

        return [
            'examId' => $exam->id,
            'alreadyAttempted' => $attempts->isNotEmpty(),
            'attemptsUsed' => $attempts->count(),
            'maxAttempts' => 1,
            'remainingAttempts' => $attempts->isNotEmpty() ? 0 : 1,
            'canAttempt' => $attempts->isEmpty(),
            'bestResult' => $latestAttempt ? $this->formatAttemptSummary($latestAttempt) : null,
            'latestAttempt' => $latestAttempt ? $this->formatAttemptSummary($latestAttempt) : null,
            'attempts' => $attempts->map(fn (ExamAttempt $attempt) => $this->formatAttemptSummary($attempt))->values(),
        ];
    }

    public function show(Exam $exam)
    {
        $this->authorizeExamView(request()->user(), $exam);

        return new ExamResource($exam->loadMissing('course'));
    }

    public function progress(Request $request, Exam $exam)
    {
        $this->authorizeExamView($request->user(), $exam);

        return response()->json([
            'data' => $this->buildProgressPayload($exam, $request->user()->id),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'duration_minutes' => 'required|integer|min:1',
            'passing_score' => 'nullable|integer|min:0|max:100',
            'questions' => 'nullable|array',
        ]);

        $this->authorizeExamCourseAccess($request->user(), (int) $validated['course_id']);

        $exam = Exam::create($validated);

        return new ExamResource($exam->loadMissing('course'));
    }

    public function update(Request $request, Exam $exam)
    {
        $validated = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|integer|min:0|max:100',
            'questions' => 'nullable|array',
        ])->after(function ($validator) use ($request, $exam) {
            $startDate = $request->filled('start_date') ? $request->date('start_date') : $exam->start_date;
            $endDate = $request->filled('end_date') ? $request->date('end_date') : $exam->end_date;

            if ($startDate && $endDate && $endDate->lte($startDate)) {
                $validator->errors()->add('end_date', 'The end date must be after the start date.');
            }
        })->validate();

        $this->authorizeExamManagement($request->user(), $exam);

        $exam->update($validated);

        return new ExamResource($exam->loadMissing('course'));
    }

    public function destroy(Exam $exam)
    {
        $this->authorizeExamManagement(request()->user(), $exam);

        $exam->delete();
        return response()->json(['message' => 'Exam deleted successfully.']);
    }

    public function submitAttempt(Request $request, Exam $exam)
    {
        $user = $request->user();
        $this->authorizeExamView($user, $exam);

        // 1. Enforce student is enrolled in the exam's course
        $enrolled = Enrollment::where('course_id', $exam->course_id)
            ->where('student_id', $user->id)
            ->exists();

        if (!$enrolled && ! $user->isAdmin() && ! $user->isTeacher()) {
            return response()->json(['message' => 'You must be enrolled in the course to take this exam.'], 403);
        }

        // 2. Enforce single-attempt rule
        $existingAttempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($existingAttempt) {
            return response()->json([
                'message' => 'You have already attempted this exam.',
                'progress' => $this->buildProgressPayload($exam, $user->id),
            ], 400);
        }

        // 3. Enforce the time window rule [startDate, endDate]
        $now = now();
        if ($now->lt($exam->start_date) || $now->gt($exam->end_date)) {
            return response()->json(['message' => 'This exam is not currently active.'], 400);
        }

        $request->validate([
            'answers' => 'required|array',
        ]);

        $submittedAnswers = $request->input('answers');
        $examQuestions = $exam->questions ?? [];
        $totalQuestions = count($examQuestions);
        $correctCount = 0;

        if ($totalQuestions > 0) {
            foreach ($examQuestions as $index => $question) {
                $correctOption = $question['correct_option'] ?? null;
                $submittedOption = $submittedAnswers[$index] ?? null;
                if ($submittedOption !== null && $submittedOption == $correctOption) {
                    $correctCount++;
                }
            }
            $score = round(($correctCount / $totalQuestions) * 100);
        } else {
            $score = 100;
        }

        $status = $score >= $exam->passing_score ? 'passed' : 'failed';

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'answers' => $submittedAnswers,
            'score' => $score,
            'status' => $status,
            'started_at' => $now->subMinutes(min($exam->duration_minutes, 10)), // mock start time slightly in the past
            'submitted_at' => $now,
        ]);

        return response()->json([
            'message' => 'Exam attempt submitted successfully.',
            'attempt' => $attempt,
            'progress' => $this->buildProgressPayload($exam, $user->id),
        ], 201);
    }

    public function attempts(Exam $exam)
    {
        $this->authorizeExamManagement(request()->user(), $exam);

        $attempts = ExamAttempt::where('exam_id', $exam->id)->with('user')->get();
        return response()->json(['data' => $attempts]);
    }



    public function byCourse(\App\Models\Course $course)
    {
        $this->authorizeExamCourseAccess(request()->user(), $course->id);

        $exams = \App\Models\Exam::where('course_id', $course->id)
            ->with('course')
            ->latest()
            ->paginate(request('per_page', 15));

        return ExamResource::collection($exams);
    }

    private function authorizeExamManagement(?User $user, Exam $exam): void
    {
        $exam->loadMissing('course');
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && (int) $exam->course?->teacher_id === (int) $user->id,
            403,
            'Forbidden.'
        );
    }

    private function authorizeExamCourseAccess(?User $user, int $courseId): void
    {
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && \App\Models\Course::query()
                ->whereKey($courseId)
                ->where('teacher_id', $user->id)
                ->exists(),
            403,
            'Forbidden.'
        );
    }

    private function authorizeExamView(?User $user, Exam $exam): void
    {
        $exam->loadMissing('course');
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin() || (int) $exam->course?->teacher_id === (int) $user->id) {
            return;
        }

        abort_unless(
            $user->isStudent()
            && Enrollment::query()
                ->where('student_id', $user->id)
                ->where('course_id', $exam->course_id)
                ->exists(),
            403,
            'Forbidden.'
        );
    }
}
