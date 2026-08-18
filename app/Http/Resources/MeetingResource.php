<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'room_name' => $this->room_name,
            'scheduled_at' => $this->scheduled_at,
            'course' => [
                'id' => $this->course?->id,
                'title' => $this->course?->title,
                'slug' => $this->course?->slug,
            ],
            'teacher' => [
                'id' => $this->teacher?->id,
                'name' => $this->teacher?->name,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
