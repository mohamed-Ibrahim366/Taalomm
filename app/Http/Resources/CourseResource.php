<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'title' => $this->title,

            'slug' => $this->slug,

            'description' => $this->description,

            'thumbnail' => $this->thumbnail
                ? asset('storage/'.$this->thumbnail)
                : null,

            'price' => $this->price,

            'currency' => $this->currency,

            'duration' => $this->duration,

            'level' => $this->level,

            'featured' => $this->is_featured,

            'is_published' => $this->is_published,

            'teacher' => [

                'id' => $this->teacher->id,

                'name' => $this->teacher->name,

            ],

            'category' => [

                'id' => $this->category->id,

                'name' => $this->category->name,

            ],

            'sections' => CourseSectionResource::collection($this->whenLoaded('sections')),

            // 'lessons_count' => $this->lessons()->count(),
            // 'students_count' => $this->enrollments()->count(),
            'lessons_count' => $this->lessons_count,
            'students_count' => $this->enrollments_count,

            'created_at' => $this->created_at,
        ];
    }
}