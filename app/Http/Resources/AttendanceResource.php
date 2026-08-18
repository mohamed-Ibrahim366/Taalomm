<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'student' => new UserResource($this->whenLoaded('student')),
            'date' => $this->date,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
