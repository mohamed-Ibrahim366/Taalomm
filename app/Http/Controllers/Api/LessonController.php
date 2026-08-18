<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    public function show(Lesson $lesson)
    {
        $this->authorizeLessonAccess(request()->user(), $lesson);

        return new LessonResource($lesson);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_section_id' => 'required|exists:course_sections,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'nullable|string|max:255',
            'duration' => 'nullable|integer',
            'order' => 'nullable|integer',
            'is_preview' => 'nullable|boolean',
        ]);

        if (!isset($validated['order'])) {
            $validated['order'] = Lesson::where('course_section_id', $validated['course_section_id'])->count() + 1;
        }

        $this->authorizeCourseSectionAccess($request->user(), (int) $validated['course_section_id']);

        $lesson = Lesson::create($validated);

        return new LessonResource($lesson);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $this->authorizeLessonAccess($request->user(), $lesson);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'nullable|string|max:255',
            'duration' => 'nullable|integer',
            'order' => 'nullable|integer',
            'is_preview' => 'nullable|boolean',
        ]);

        $lesson->update($validated);

        return new LessonResource($lesson);
    }

    public function destroy(Lesson $lesson)
    {
        $this->authorizeLessonAccess(request()->user(), $lesson);

        $lesson->delete();
        return response()->json(['message' => 'Lesson deleted successfully.']);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'lessons' => 'required|array',
            'lessons.*.id' => 'required|exists:lessons,id',
            'lessons.*.order' => 'required|integer',
        ]);

        $lesson = Lesson::with('section.course')->findOrFail((int) $request->input('lessons.0.id'));
        $this->authorizeLessonAccess($request->user(), $lesson);

        foreach ($request->input('lessons') as $item) {
            Lesson::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Lessons reordered successfully.']);
    }

    public function uploadVideo(Request $request, Lesson $lesson)
    {
        $this->authorizeLessonAccess($request->user(), $lesson);

        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/mpeg,video/quicktime|max:51200', // max 50MB
        ]);

        if ($request->hasFile('video')) {
            // Delete old video if any
            if ($lesson->video_url && Storage::disk('public')->exists($lesson->video_url)) {
                Storage::disk('public')->delete($lesson->video_url);
            }

            $path = $request->file('video')->store('lessons/videos', 'public');
            $lesson->update(['video_url' => $path]);

            return response()->json([
                'message' => 'Video uploaded successfully.',
                'video_url' => asset('storage/' . $path),
            ]);
        }

        return response()->json(['message' => 'Failed to upload video.'], 400);
    }

    private function authorizeLessonAccess(?User $user, Lesson $lesson): void
    {
        $lesson->loadMissing('section.course');
        abort_unless($user, 403, 'Forbidden.');

        if ($user->isAdmin()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && (int) $lesson->section?->course?->teacher_id === (int) $user->id,
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
