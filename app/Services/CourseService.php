<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseService
{
    public function paginate(array $filters)
    {
        return Course::query()
            ->with(['teacher:id,name', 'category:id,name'])
            ->withCount(['lessons', 'enrollments'])
            ->when(
                $filters['search'] ?? null,
                fn($q, $search) =>
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                })
            )
            ->when(
                $filters['category_id'] ?? null,
                fn($q, $id) => $q->where('category_id', $id)
            )
            ->when(
                $filters['teacher_id'] ?? null,
                fn($q, $id) => $q->where('teacher_id', $id)
            )
            ->when(
                $filters['level'] ?? null,
                fn($q, $level) => $q->where('level', $level)
            )
            ->latest()
            ->paginate(15);
    }



    public function create(array $data): Course
    {
        return DB::transaction(function () use ($data) {

            $data['teacher_id'] = auth()->id();

            if (blank($data['slug'] ?? null)) {
                $data['slug'] = Str::slug($data['title']);
            }

            if (isset($data['thumbnail'])) {
                $data['thumbnail'] = $this->uploadThumbnail($data['thumbnail']);
            }

            return Course::create($data);
        });
    }

    public function update(Course $course, array $data): Course
    {
        return DB::transaction(function () use ($course, $data) {

            if (blank($data['slug'] ?? null)) {
                $data['slug'] = Str::slug($data['title']);
            }

            if (isset($data['thumbnail'])) {

                if ($course->thumbnail) {
                    Storage::disk('public')->delete($course->thumbnail);
                }

                $data['thumbnail'] = $this->uploadThumbnail($data['thumbnail']);
            }

            $course->update($data);

            return $course->refresh();
        });
    }


    public function delete(Course $course): void
    {
        if ($course->thumbnail) {
            Storage::disk('public')->delete($course->thumbnail);
        }

        $course->delete();
    }

    private function uploadThumbnail(UploadedFile $file): string
    {
        return $file->store('courses', 'public');
    }


    public function publish(Course $course): Course
    {
        $course->update([
            'is_published' => true
        ]);

        return $course->refresh();
    }


    public function unpublish(Course $course): Course
    {
        $course->update([
            'is_published' => false
        ]);

        return $course->refresh();
    }


    public function statistics()
    {
        $teacherId = auth()->id();


        return [

            'courses' => Course::whereTeacherId($teacherId)->count(),

            'published_courses' => Course::whereTeacherId($teacherId)
                ->where('is_published', true)
                ->count(),

            'draft_courses' => Course::whereTeacherId($teacherId)
                ->where('is_published', false)
                ->count(),

            'featured_courses' => Course::whereTeacherId($teacherId)
                ->where('is_featured', true)
                ->count(),
        ];
    }


}
