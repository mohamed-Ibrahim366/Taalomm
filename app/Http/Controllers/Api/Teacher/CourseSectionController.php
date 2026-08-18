<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseSectionController extends Controller
{
    /**
     * Store a newly created section.
     */
    public function store(Request $request, Course $course)
    {
        $this->authorize('create', [CourseSection::class, $course]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_published' => 'boolean',
            'order' => 'integer',
        ]);

        if (!isset($validated['order'])) {
            $validated['order'] = $course->sections()->max('order') + 1;
        }

        $section = $course->sections()->create($validated);

        return response()->json([
            'message' => 'Section created successfully.',
            'section' => $section,
        ], 201);
    }

    /**
     * Update the specified section.
     */
    public function update(Request $request, CourseSection $section)
    {
        $this->authorize('update', $section);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_published' => 'boolean',
            'order' => 'integer',
        ]);

        $section->update($validated);

        return response()->json([
            'message' => 'Section updated successfully.',
            'section' => $section,
        ]);
    }

    /**
     * Remove the specified section.
     */
    public function destroy(CourseSection $section)
    {
        $this->authorize('delete', $section);

        $section->delete();

        return response()->json([
            'message' => 'Section deleted successfully.',
        ]);
    }

    /**
     * Reorder sections.
     * Expects: { sections: [ { id: X, order: Y }, ... ] }
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:course_sections,id',
            'sections.*.order' => 'required|integer',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['sections'] as $item) {
                $section = CourseSection::findOrFail($item['id']);
                $this->authorize('update', $section);
                $section->update(['order' => $item['order']]);
            }
        });

        return response()->json([
            'message' => 'Sections reordered successfully.',
        ]);
    }
}
