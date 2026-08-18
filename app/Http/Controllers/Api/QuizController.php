<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuizResource;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuizController extends Controller
{
    private function normalizeRequest(Request $request): void
    {
        $payload = [
            'course_id' => $request->input('course_id', $request->input('courseId')),
            'lesson_id' => $request->input('lesson_id', $request->input('lessonId')),
            'time_limit' => $request->input('time_limit', $request->input('timeLimit')),
            'passing_score' => $request->input('passing_score', $request->input('passingScore')),
            'max_attempts' => $request->input('max_attempts', $request->input('maxAttempts')),
            'is_published' => $request->has('is_published')
                ? filter_var($request->input('is_published'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : ($request->has('isPublished') ? filter_var($request->input('isPublished'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null),
        ];

        if ($request->exists('questions')) {
            $payload['questions'] = $this->decodeQuestionsInput($request->input('questions'));
        }

        $request->merge($payload);
    }

    private function decodeQuestionsInput(mixed $questions): array
    {
        if (is_string($questions)) {
            $decoded = json_decode($questions, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return is_array($decoded) ? $decoded : [];
            }
        }

        return is_array($questions) ? $questions : [];
    }

    private function resolveAssociation(Request $request, ?Quiz $quiz = null): array
    {
        $courseId = $request->filled('course_id') ? (int) $request->input('course_id') : $quiz?->course_id;
        $lessonId = $request->filled('lesson_id') ? (int) $request->input('lesson_id') : $quiz?->lesson_id;
        $courseSectionId = $request->input('course_section_id')
            ?? $request->input('section_id')
            ?? $quiz?->course_section_id;

        $section = $courseSectionId ? CourseSection::find((int) $courseSectionId) : null;
        $lesson = $lessonId ? Lesson::find($lessonId) : null;
        $course = $courseId ? Course::find($courseId) : null;

        if ($lesson && !$course) {
            $course = $lesson->course;
            $courseId = $course?->id;
        }

        if ($section && !$course) {
            $course = $section->course;
            $courseId = $course?->id;
        }

        if (!$lesson && $quiz?->lesson) {
            $lesson = $quiz->lesson;
            $lessonId = $lesson?->id;
        }

        if ($course && $lesson && $lesson->course_id !== $course->id) {
            return [
                'error' => response()->json([
                    'message' => 'The selected lesson does not belong to the selected course.',
                    'errors' => [
                        'lesson_id' => ['The selected lesson does not belong to the selected course.'],
                    ],
                ], 422),
            ];
        }

        if (!$courseId && $courseSectionId) {
            $courseId = $section?->course_id ?? $quiz?->course_id ?? $request->input('course_id');
        }

        return [
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
            'course_section_id' => $courseSectionId ? (int) $courseSectionId : null,
        ];
    }

    private function normalizeQuestion(array $question, int $order, int $quizId, ?int $fallbackId = null): array
    {
        $options = $question['options'] ?? [];
        if (is_string($options)) {
            $options = preg_split('/\r\n|\r|\n/', $options) ?: [];
        }

        $options = array_values(array_filter(array_map(
            static fn ($option) => trim((string) $option),
            is_array($options) ? $options : []
        ), static fn ($option) => $option !== ''));

        $correctAnswer = $question['correctAnswer'] ?? $question['correct_answer'] ?? $question['answer'] ?? null;
        if (array_key_exists('correct_option', $question)) {
            $correctAnswer = $question['correct_option'];
        }

        $type = $question['type'] ?? 'multiple_choice';
        if ($type === 'true_false' && empty($options)) {
            $options = ['صح', 'خطأ'];
        }

        if (in_array($type, ['short_answer', 'essay'], true)) {
            $options = [];
        }

        return [
            'id' => isset($question['id']) ? (int) $question['id'] : ($fallbackId ?? $order),
            'quizId' => $quizId,
            'text' => trim((string) ($question['text'] ?? '')),
            'type' => in_array($type, ['multiple_choice', 'true_false', 'short_answer', 'essay'], true)
                ? $type
                : 'multiple_choice',
            'options' => $options,
            'correctAnswer' => is_array($correctAnswer) ? array_values($correctAnswer) : (string) $correctAnswer,
            'points' => max(1, (int) ($question['points'] ?? 1)),
            'order' => (int) ($question['order'] ?? $order),
        ];
    }

    private function normalizeQuestionsList(array $questions, int $quizId): array
    {
        $normalized = [];
        $nextId = 1;

        foreach ($questions as $index => $question) {
            if (!is_array($question)) {
                continue;
            }

            $item = $this->normalizeQuestion($question, $index + 1, $quizId, $nextId);
            $normalized[] = $item;
            $nextId = max($nextId, $item['id'] + 1);
        }

        usort($normalized, function (array $left, array $right) {
            return [$left['order'], $left['id']] <=> [$right['order'], $right['id']];
        });

        foreach ($normalized as $index => &$question) {
            $question['order'] = $index + 1;
            $question['quizId'] = $quizId;
        }
        unset($question);

        return $normalized;
    }

    private function extractSubmittedAnswer(array $answers, array $question, int $index): mixed
    {
        if (array_is_list($answers)) {
            return $answers[$index] ?? null;
        }

        $candidates = [];

        if (isset($question['id'])) {
            $candidates[] = (string) $question['id'];
        }

        if (isset($question['order'])) {
            $candidates[] = (string) $question['order'];
        }

        $candidates[] = (string) $index;
        $candidates[] = (string) ($index + 1);

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $answers)) {
                return $answers[$candidate];
            }
        }

        foreach ($answers as $answer) {
            if (!is_array($answer)) {
                continue;
            }

            $answerQuestionId = $answer['questionId'] ?? $answer['question_id'] ?? null;
            if ($answerQuestionId !== null && (int) $answerQuestionId === (int) ($question['id'] ?? 0)) {
                return $answer['selectedOption']
                    ?? $answer['selectedOptions']
                    ?? $answer['textResponse']
                    ?? $answer['answer']
                    ?? null;
            }
        }

        return null;
    }

    private function answersMatch(mixed $submittedAnswer, array $question): bool
    {
        $correctAnswer = $question['correctAnswer'] ?? null;

        if ($submittedAnswer === null || $correctAnswer === null || $correctAnswer === '') {
            return false;
        }

        if (is_array($correctAnswer)) {
            $submittedValues = is_array($submittedAnswer) ? $submittedAnswer : [$submittedAnswer];
            $submittedValues = array_map(static fn ($value) => trim((string) $value), $submittedValues);
            $correctValues = array_map(static fn ($value) => trim((string) $value), $correctAnswer);

            sort($submittedValues);
            sort($correctValues);

            return $submittedValues === $correctValues;
        }

        if (is_array($submittedAnswer)) {
            $submittedAnswer = reset($submittedAnswer);
        }

        return mb_strtolower(trim((string) $submittedAnswer)) === mb_strtolower(trim((string) $correctAnswer));
    }

    private function gradeQuiz(Quiz $quiz, array $submittedAnswers): array
    {
        $questions = $this->normalizeQuestionsList($quiz->questions ?? [], $quiz->id);
        $totalScore = array_sum(array_map(static fn (array $question) => (int) $question['points'], $questions));
        $earnedScore = 0;

        foreach ($questions as $index => $question) {
            $submittedAnswer = $this->extractSubmittedAnswer($submittedAnswers, $question, $index);
            if ($this->answersMatch($submittedAnswer, $question)) {
                $earnedScore += (int) $question['points'];
            }
        }

        $percentage = $totalScore > 0 ? (int) round(($earnedScore / $totalScore) * 100) : 0;

        return [
            'score' => $earnedScore,
            'total_score' => $totalScore,
            'percentage' => $percentage,
            'passed' => $totalScore === 0 ? true : $percentage >= (int) ($quiz->passing_score ?? 0),
        ];
    }

    private function formatSubmissionSummary(QuizSubmission $submission): array
    {
        $totalScore = (int) ($submission->total_score ?? 0);
        $score = (int) ($submission->score ?? 0);
        $percentage = $totalScore > 0 ? (int) round(($score / $totalScore) * 100) : 0;

        return [
            'submissionId' => $submission->id,
            'attemptNumber' => (int) $submission->attempt_number,
            'score' => $percentage,
            'rawScore' => $score,
            'totalScore' => $totalScore,
            'percentage' => $percentage,
            'passed' => $submission->status === 'passed',
            'status' => $submission->status,
            'feedback' => $submission->feedback,
            'submittedAt' => $submission->submitted_at?->toIso8601String() ?? $submission->created_at?->toIso8601String(),
        ];
    }

    private function buildProgressPayload(Quiz $quiz, int $userId): array
    {
        $submissions = QuizSubmission::where('quiz_id', $quiz->id)
            ->where('user_id', $userId)
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $attemptsUsed = $submissions->count();
        $maxAttempts = (int) ($quiz->max_attempts ?? 1);
        $latestAttempt = $submissions->last();

        $bestSubmission = $submissions->sort(function (QuizSubmission $left, QuizSubmission $right) {
            $leftPercentage = (int) ($left->total_score ?? 0) > 0
                ? ($left->score / $left->total_score) * 100
                : 0;
            $rightPercentage = (int) ($right->total_score ?? 0) > 0
                ? ($right->score / $right->total_score) * 100
                : 0;

            $comparison = $rightPercentage <=> $leftPercentage;
            if ($comparison !== 0) {
                return $comparison;
            }

            return ($right->submitted_at?->timestamp ?? $right->created_at?->timestamp ?? 0)
                <=> ($left->submitted_at?->timestamp ?? $left->created_at?->timestamp ?? 0);
        })->first();

        return [
            'quizId' => $quiz->id,
            'alreadyAttempted' => $attemptsUsed > 0,
            'attemptsUsed' => $attemptsUsed,
            'maxAttempts' => $maxAttempts,
            'remainingAttempts' => max(0, $maxAttempts - $attemptsUsed),
            'canAttempt' => $attemptsUsed < $maxAttempts,
            'bestResult' => $bestSubmission ? $this->formatSubmissionSummary($bestSubmission) : null,
            'latestAttempt' => $latestAttempt ? $this->formatSubmissionSummary($latestAttempt) : null,
            'attempts' => $submissions->map(fn (QuizSubmission $submission) => $this->formatSubmissionSummary($submission))->values(),
        ];
    }

    private function updateQuizQuestions(Quiz $quiz, array $questions): void
    {
        $quiz->update([
            'questions' => $this->normalizeQuestionsList($questions, $quiz->id),
        ]);
    }

    public function show(Quiz $quiz)
    {
        $this->authorizeQuizView(request()->user(), $quiz);

        return new QuizResource($quiz->loadMissing(['course', 'lesson', 'section.course']));
    }

    public function progress(Request $request, Quiz $quiz)
    {
        $this->authorizeQuizView($request->user(), $quiz);

        return response()->json([
            'data' => $this->buildProgressPayload($quiz, $request->user()->id),
        ]);
    }

    public function store(Request $request)
    {
        $this->normalizeRequest($request);

        $validated = $request->validate([
            'course_id' => 'nullable|exists:courses,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'course_section_id' => 'nullable|exists:course_sections,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'passing_score' => 'nullable|integer|min:0|max:100',
            'time_limit' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:1',
            'is_published' => 'nullable|boolean',
            'questions' => 'nullable|array',
        ]);

        $association = $this->resolveAssociation($request);
        if (isset($association['error'])) {
            return $association['error'];
        }

        $this->authorizeQuizCourseAccess($request->user(), (int) $association['course_id']);

        if (!$association['course_id']) {
            return response()->json([
                'message' => 'Please select a course or lesson before creating the quiz.',
                'errors' => [
                    'course_id' => ['Unable to determine the quiz course.'],
                ],
            ], 422);
        }

        if (!empty($validated['questions'])) {
            $validated['questions'] = $this->normalizeQuestionsList($validated['questions'], 0);
        }

        $quiz = Quiz::create([
            'course_id' => $association['course_id'],
            'lesson_id' => $association['lesson_id'],
            'course_section_id' => $association['course_section_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'passing_score' => $validated['passing_score'] ?? 50,
            'time_limit' => $validated['time_limit'] ?? 0,
            'max_attempts' => $validated['max_attempts'] ?? 1,
            'is_published' => $validated['is_published'] ?? true,
            'questions' => $validated['questions'] ?? [],
        ]);

        return new QuizResource($quiz->loadMissing(['course', 'lesson', 'section.course']));
    }

    public function update(Request $request, Quiz $quiz)
    {
        $this->normalizeRequest($request);

        $validated = $request->validate([
            'course_id' => 'nullable|exists:courses,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'course_section_id' => 'nullable|exists:course_sections,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'passing_score' => 'nullable|integer|min:0|max:100',
            'time_limit' => 'nullable|integer|min:0',
            'max_attempts' => 'nullable|integer|min:1',
            'is_published' => 'nullable|boolean',
            'questions' => 'nullable|array',
        ]);

        $association = $this->resolveAssociation($request, $quiz);
        if (isset($association['error'])) {
            return $association['error'];
        }

        $this->authorizeQuizCourseAccess($request->user(), (int) $association['course_id']);

        $payload = array_filter([
            'course_id' => $association['course_id'],
            'lesson_id' => $association['lesson_id'],
            'course_section_id' => $association['course_section_id'],
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'passing_score' => $validated['passing_score'] ?? null,
            'time_limit' => $validated['time_limit'] ?? null,
            'max_attempts' => $validated['max_attempts'] ?? null,
            'is_published' => $validated['is_published'] ?? null,
        ], static fn ($value) => $value !== null);

        $quiz->update($payload);

        if (array_key_exists('questions', $validated)) {
            $quiz->update([
                'questions' => $this->normalizeQuestionsList($validated['questions'] ?? [], $quiz->id),
            ]);
        }

        return new QuizResource($quiz->fresh()->loadMissing(['course', 'lesson', 'section.course']));
    }

    public function destroy(Quiz $quiz)
    {
        $this->authorizeQuizManagement(request()->user(), $quiz);

        $quiz->delete();
        return response()->json(['message' => 'Quiz deleted successfully.']);
    }

    public function submit(Request $request, Quiz $quiz)
    {
        $this->authorizeQuizView($request->user(), $quiz);

        $request->validate([
            'answers' => 'required|array',
        ]);

        $user = $request->user();
        $attemptNumber = QuizSubmission::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->count() + 1;

        if ($attemptNumber > (int) ($quiz->max_attempts ?? 1)) {
            return response()->json([
                'message' => 'You have reached the maximum number of attempts for this quiz.',
                'progress' => $this->buildProgressPayload($quiz, $user->id),
            ], 400);
        }

        $grade = $this->gradeQuiz($quiz, $request->input('answers', []));

        $submission = QuizSubmission::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'attempt_number' => $attemptNumber,
            'answers' => $request->input('answers'),
            'score' => $grade['score'],
            'total_score' => $grade['total_score'],
            'status' => $grade['passed'] ? 'passed' : 'failed',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Quiz submitted successfully.',
            'data' => [
                'id' => $submission->id,
                'quizId' => $quiz->id,
                'userId' => $user->id,
                'attemptNumber' => $submission->attempt_number,
                'score' => $submission->score,
                'totalScore' => $submission->total_score,
                'status' => $submission->status,
                'submittedAt' => $submission->submitted_at?->toIso8601String() ?? $submission->created_at?->toIso8601String(),
            ],
            'progress' => $this->buildProgressPayload($quiz, $user->id),
        ], 201);
    }

    public function storeQuestion(Request $request, Quiz $quiz)
    {
        $this->authorizeQuizManagement($request->user(), $quiz);

        $request->validate([
            'text' => 'required|string|min:3',
            'type' => 'required|in:multiple_choice,true_false,short_answer,essay',
            'options' => 'nullable|array',
            'correctAnswer' => 'required',
            'points' => 'required|integer|min:1',
            'order' => 'nullable|integer|min:1',
        ]);

        $questions = $this->normalizeQuestionsList($quiz->questions ?? [], $quiz->id);
        $nextId = collect($questions)->pluck('id')->max() + 1;
        $order = (int) ($request->input('order') ?? (count($questions) + 1));
        $question = $this->normalizeQuestion($request->all(), $order, $quiz->id, $nextId ?: 1);

        $questions[] = $question;
        $this->updateQuizQuestions($quiz, $questions);

        return response()->json([
            'message' => 'Question added successfully.',
            'data' => new QuizResource($quiz->fresh()->loadMissing(['course', 'lesson', 'section.course'])),
        ], 201);
    }

    public function updateQuestion(Request $request, Quiz $quiz, int $question)
    {
        $this->authorizeQuizManagement($request->user(), $quiz);

        $request->validate([
            'text' => 'nullable|string|min:3',
            'type' => 'nullable|in:multiple_choice,true_false,short_answer,essay',
            'options' => 'nullable|array',
            'correctAnswer' => 'nullable',
            'points' => 'nullable|integer|min:1',
            'order' => 'nullable|integer|min:1',
        ]);

        $questions = $this->normalizeQuestionsList($quiz->questions ?? [], $quiz->id);
        $index = collect($questions)->search(fn (array $item) => (int) $item['id'] === (int) $question);

        if ($index === false) {
            return response()->json([
                'message' => 'Question not found.',
            ], 404);
        }

        $questions[$index] = $this->normalizeQuestion(
            array_merge($questions[$index], array_filter($request->all(), static fn ($value) => $value !== null)),
            (int) ($request->input('order') ?? $questions[$index]['order']),
            $quiz->id,
            (int) $questions[$index]['id']
        );

        $this->updateQuizQuestions($quiz, $questions);

        return response()->json([
            'message' => 'Question updated successfully.',
            'data' => new QuizResource($quiz->fresh()->loadMissing(['course', 'lesson', 'section.course'])),
        ]);
    }

    public function destroyQuestion(Quiz $quiz, int $question)
    {
        $this->authorizeQuizManagement(request()->user(), $quiz);

        $questions = $this->normalizeQuestionsList($quiz->questions ?? [], $quiz->id);
        $filtered = array_values(array_filter($questions, fn (array $item) => (int) $item['id'] !== (int) $question));

        if (count($filtered) === count($questions)) {
            return response()->json([
                'message' => 'Question not found.',
            ], 404);
        }

        foreach ($filtered as $index => &$item) {
            $item['order'] = $index + 1;
        }
        unset($item);

        $this->updateQuizQuestions($quiz, $filtered);

        return response()->json([
            'message' => 'Question deleted successfully.',
            'data' => new QuizResource($quiz->fresh()->loadMissing(['course', 'lesson', 'section.course'])),
        ]);
    }

    public function submissions(Quiz $quiz)
    {
        $this->authorizeQuizManagement(request()->user(), $quiz);

        $submissions = QuizSubmission::where('quiz_id', $quiz->id)->with('user')->latest()->get();
        return response()->json(['data' => $submissions]);
    }

    public function grade(Request $request, QuizSubmission $submission)
    {
        $submission->loadMissing('quiz.course', 'quiz.section.course');
        $this->authorizeQuizManagement($request->user(), $submission->quiz);

        $request->validate([
            'score' => 'required|integer|min:0',
            'feedback' => 'nullable|string',
        ]);

        $quiz = $submission->quiz;
        $totalScore = $submission->total_score ?: array_sum(array_map(
            static fn (array $question) => (int) ($question['points'] ?? 0),
            $quiz->questions ?? []
        ));
        $percentage = $totalScore > 0 ? (int) round(($request->input('score') / $totalScore) * 100) : 0;
        $status = $totalScore === 0 || $percentage >= (int) ($quiz->passing_score ?? 50) ? 'passed' : 'failed';

        $submission->update([
            'score' => $request->input('score'),
            'total_score' => $totalScore,
            'status' => $status,
            'feedback' => $request->feedback,
        ]);

        return response()->json([
            'message' => 'Submission graded successfully.',
            'submission' => $submission,
        ]);
    }


    public function byCourse(Course $course)
    {
        $this->authorizeQuizCourseAccess(request()->user(), $course->id);

        $quizzes = Quiz::query()
            ->where(function ($query) use ($course) {
                $query->where('course_id', $course->id)
                    ->orWhereHas('section', function ($sectionQuery) use ($course) {
                        $sectionQuery->where('course_id', $course->id);
                    });
            })
            ->with(['course', 'lesson', 'section.course'])
            ->latest()
            ->paginate(request('per_page', 15));

        return QuizResource::collection($quizzes);
    }

    private function authorizeQuizManagement(?User $user, Quiz $quiz): void
    {
        $quiz->loadMissing(['course', 'section.course']);

        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && (int) ($quiz->course?->teacher_id ?? $quiz->section?->course?->teacher_id) === (int) $user->id,
            403,
            'Forbidden.'
        );
    }

    private function authorizeQuizCourseAccess(?User $user, int $courseId): void
    {
        abort_unless($courseId > 0, 403, 'Forbidden.');
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && Course::query()
                ->whereKey($courseId)
                ->where('teacher_id', $user->id)
                ->exists(),
            403,
            'Forbidden.'
        );
    }

    private function authorizeQuizView(?User $user, Quiz $quiz): void
    {
        $quiz->loadMissing(['course', 'section.course']);

        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        $courseId = (int) ($quiz->course_id ?? $quiz->section?->course_id ?? 0);

        if ($user->isTeacher()) {
            abort_unless(
                (int) ($quiz->course?->teacher_id ?? $quiz->section?->course?->teacher_id) === (int) $user->id,
                403,
                'Forbidden.'
            );

            return;
        }

        abort_unless(
            $quiz->is_published
            && $courseId > 0
            && Enrollment::query()
                ->where('student_id', $user->id)
                ->where('course_id', $courseId)
                ->exists(),
            403,
            'Forbidden.'
        );
    }
}
