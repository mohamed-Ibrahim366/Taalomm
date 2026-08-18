<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'teacher' => $this->teacher ? new UserResource($this->teacher) : null,
            'course' => $this->course ? [
                'id' => $this->course->id,
                'title' => $this->course->title,
            ] : null,
            'schedule' => GroupSessionResource::collection($this->whenLoaded('sessions')),
            'students' => UserResource::collection($this->whenLoaded('students')),
            'created_at' => $this->created_at,
        ];
    }
}
