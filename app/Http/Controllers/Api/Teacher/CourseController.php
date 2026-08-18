<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Course\StoreCourseRequest;
use App\Http\Requests\Course\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(
        private readonly CourseService $service
    ) {}

    public function index(Request $request)
    {
        return CourseResource::collection(
            $this->service->paginate($request->all())
        );
    }

    public function store(StoreCourseRequest $request)
    {
        $course = $this->service->create($request->validated());

        return new CourseResource($course);
    }

    public function show(Course $course)
    {
        $this->authorize('view', $course);
        return new CourseResource(
            $course->load([
                'teacher',
                'category',
                'sections.lessons',
            ])
        );
    }

    public function update(UpdateCourseRequest $request, Course $course) 
    {
        $this->authorize('update', $course);
        return new CourseResource(
            $this->service->update(
                $course,
                $request->validated()
            )
        );
    }

    public function destroy(Course $course)
    {
        $this->authorize('delete', $course);
        $this->service->delete($course);

        return response()->json([
            'message' => 'Course deleted successfully'
        ]);
    }


    public function publish(Course $course)
    {
        $this->authorize('publish', $course);

        return new CourseResource(
            $this->service->publish($course)
        );
    }


    public function unpublish(Course $course)
    {
        $this->authorize('publish', $course);

        return new CourseResource(
            $this->service->unpublish($course)
        );
    }

public function statistics()
{
    return response()->json(
        $this->service->statistics()
    );
}


}
