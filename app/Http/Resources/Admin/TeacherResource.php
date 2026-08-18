<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'phone' => $this->phone,
            'photo_url' => $this->photo_path ? asset('storage/' . $this->photo_path) : null,
            'governorate' => $this->governorate,
            'grade_level' => $this->grade_level,
            'email_verified_at' => $this->email_verified_at,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'courses_count' => $this->courses_count ?? 0,
            'students_count' => $this->students_count ?? 0,
        ];
    }
}
