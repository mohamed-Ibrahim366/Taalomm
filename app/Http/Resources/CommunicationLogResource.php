<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommunicationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent' => new UserResource($this->whenLoaded('parent')),
            'teacher' => new UserResource($this->whenLoaded('teacher')),
            'message' => $this->message,
            'type' => $this->type,
            'logged_at' => $this->logged_at,
            'created_at' => $this->created_at,
        ];
    }
}
