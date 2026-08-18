<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_section_id' => $this->course_section_id,
            'title' => $this->title,
            'description' => $this->description,
            'video_url' => $this->video_url,
            'duration' => $this->duration,
            'order' => $this->order,
            'is_preview' => $this->is_preview,
        ];
    }
}
