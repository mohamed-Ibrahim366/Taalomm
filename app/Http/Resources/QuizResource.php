<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $questions = $this->questions ?? [];
        $courseId = $this->course_id ?: $this->section?->course_id;
        $lessonId = $this->lesson_id;

        return [
            'id' => $this->id,
            'courseId' => $courseId,
            'lessonId' => $lessonId,
            'timeLimit' => $this->time_limit,
            'passingScore' => $this->passing_score,
            'maxAttempts' => $this->max_attempts ?? 1,
            'isPublished' => (bool) ($this->is_published ?? true),
            'questionsCount' => count($questions),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'questions' => $questions,
            'course' => $this->whenLoaded('course', fn () => [
                'id' => $this->course?->id,
                'title' => $this->course?->title,
                'slug' => $this->course?->slug,
            ]),
            'lesson' => $this->whenLoaded('lesson', fn () => [
                'id' => $this->lesson?->id,
                'title' => $this->lesson?->title,
                'course_section_id' => $this->lesson?->course_section_id,
            ]),
            'section' => $this->whenLoaded('section', fn () => new CourseSectionResource($this->section)),

            // Backward-compatible snake_case fields
            'course_id' => $courseId,
            'lesson_id' => $lessonId,
            'time_limit' => $this->time_limit,
            'passing_score' => $this->passing_score,
            'max_attempts' => $this->max_attempts ?? 1,
            'is_published' => (bool) ($this->is_published ?? true),
            'questions_count' => count($questions),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'course_section_id' => $this->course_section_id,
            'title' => $this->title,
            'description' => $this->description,
        ];
    }
}
