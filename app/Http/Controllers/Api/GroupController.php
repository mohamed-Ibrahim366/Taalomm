<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Models\Course;
use App\Models\User;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    private const VALID_DAYS = [
        'saturday',
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
    ];

    public function index()
    {
        $user = request()->user();

        $groups = Group::query()
            ->with(['teacher', 'course', 'sessions', 'students'])
            ->when($user && $user->isTeacher() && ! $user->isAdmin(), function ($query) use ($user) {
                $query->where(function ($inner) use ($user) {
                    $inner->where('teacher_id', $user->id)
                        ->orWhereHas('course', function ($courseQuery) use ($user) {
                            $courseQuery->where('teacher_id', $user->id);
                        });
                });
            })
            ->latest()
            ->get();

        return GroupResource::collection($groups);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'teacher_id' => 'nullable|exists:users,id',
            'course_id' => 'nullable|exists:courses,id',
            'students' => 'nullable|array',
            'students.*' => 'exists:users,id',
            'schedule' => 'nullable|array',
            'schedule.*.day' => ['required_with:schedule', 'string', Rule::in(self::VALID_DAYS)],
            'schedule.*.start_time' => 'required_with:schedule|date_format:H:i',
            'schedule.*.end_time' => 'required_with:schedule|date_format:H:i',
        ]);

        $this->assertValidSchedule($validated['schedule'] ?? []);

        $this->authorizeGroupPayload($request->user(), $validated['teacher_id'] ?? null, $validated['course_id'] ?? null);

        return DB::transaction(function () use ($validated) {
            $user = request()->user();
            $group = Group::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'teacher_id' => $validated['teacher_id'] ?? $user?->id,
                'course_id' => $validated['course_id'] ?? null,
            ]);

            if (!empty($validated['students'])) {
                $group->students()->sync($validated['students']);
            }

            $this->syncSchedule($group, $validated['schedule'] ?? null);

            return new GroupResource($group->load(['teacher', 'course', 'sessions', 'students']));
        });
    }

    public function show(Group $group)
    {
        $this->authorizeGroupAccess(request()->user(), $group);

        return new GroupResource($group->load(['teacher', 'course', 'sessions', 'students']));
    }

    public function update(Request $request, Group $group)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'teacher_id' => 'nullable|exists:users,id',
            'course_id' => 'nullable|exists:courses,id',
            'students' => 'nullable|array',
            'students.*' => 'exists:users,id',
            'schedule' => 'nullable|array',
            'schedule.*.day' => ['required_with:schedule', 'string', Rule::in(self::VALID_DAYS)],
            'schedule.*.start_time' => 'required_with:schedule|date_format:H:i',
            'schedule.*.end_time' => 'required_with:schedule|date_format:H:i',
        ]);

        $this->assertValidSchedule($validated['schedule'] ?? []);
        $this->authorizeGroupAccess($request->user(), $group);
        $this->authorizeGroupPayload($request->user(), $validated['teacher_id'] ?? null, $validated['course_id'] ?? null);

        $group->update(array_filter([
            'name' => array_key_exists('name', $validated) ? $validated['name'] : null,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : null,
            'teacher_id' => array_key_exists('teacher_id', $validated) ? $validated['teacher_id'] : null,
            'course_id' => array_key_exists('course_id', $validated) ? $validated['course_id'] : null,
        ], static fn ($value, $key) => array_key_exists($key, $validated), ARRAY_FILTER_USE_BOTH));

        if (array_key_exists('students', $validated)) {
            $group->students()->sync($validated['students'] ?? []);
        }

        $this->syncSchedule($group, $validated['schedule'] ?? null);

        return new GroupResource($group->load(['teacher', 'course', 'sessions', 'students']));
    }

    public function destroy(Group $group)
    {
        $this->authorizeGroupAccess(request()->user(), $group);

        $group->students()->detach();
        $group->delete();

        return response()->json(['message' => 'Group deleted successfully.']);
    }

    private function syncSchedule(Group $group, ?array $schedule): void
    {
        if ($schedule === null) {
            return;
        }

        $group->sessions()->delete();

        if ($schedule === []) {
            return;
        }

        $group->sessions()->createMany(array_map(static function (array $session): array {
            return [
                'day' => $session['day'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
            ];
            }, $schedule));
    }

    private function assertValidSchedule(array $schedule): void
    {
        foreach ($schedule as $index => $session) {
            if (!isset($session['start_time'], $session['end_time'])) {
                continue;
            }

            if ($session['end_time'] <= $session['start_time']) {
                throw ValidationException::withMessages([
                    "schedule.$index.end_time" => 'The end time must be after the start time.',
                ]);
            }
        }
    }

    private function authorizeGroupAccess(?User $user, Group $group): void
    {
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

    private function authorizeGroupPayload(?User $user, ?int $teacherId, ?int $courseId): void
    {
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        abort_unless($user->isTeacher(), 403, 'Forbidden.');

        if ($teacherId !== null) {
            abort_unless((int) $teacherId === (int) $user->id, 403, 'Forbidden.');
        }

        if ($courseId !== null) {
            abort_unless(
                Course::query()
                    ->whereKey($courseId)
                    ->where('teacher_id', $user->id)
                    ->exists(),
                403,
                'Forbidden.'
            );
        }
    }
}
