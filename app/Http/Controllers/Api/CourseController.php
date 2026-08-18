<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    /**
     * Display a listing of published courses.
     */
    public function index(Request $request)
    {
        $courses = Course::query()
            ->where('is_published', true)
            ->with(['teacher:id,name', 'category:id,name'])
            ->withCount(['lessons', 'enrollments'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->category_id, function ($q, $catId) {
                $q->where('category_id', $catId);
            })
            ->when($request->teacher_id, function ($q, $teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->when($request->level, function ($q, $level) {
                $q->where('level', $level);
            })
            ->latest()
            ->paginate($request->input('per_page', 15));

        return CourseResource::collection($courses);
    }

    /**
     * Display the specified published course.
     */
    public function show(Course $course)
    {
        if (!$course->is_published) {
            abort(404, 'Course not found or not published.');
        }

        return new CourseResource(
            $course->load([
                'teacher:id,name,phone,photo_path',
                'category:id,name',
                'sections' => function ($q) {
                    $q->where('is_published', true)->orderBy('order');
                },
                'sections.lessons' => function ($q) {
                    $q->orderBy('order');
                }
            ])
        );
    }

    /**
     * Display a list of published sections for the specified course.
     */
    public function sections(Course $course)
    {
        if (!$course->is_published) {
            abort(404, 'Course not found or not published.');
        }

        $sections = $course->sections()
            ->where('is_published', true)
            ->orderBy('order')
            ->get();

        return response()->json(['data' => $sections]);
    }
}
