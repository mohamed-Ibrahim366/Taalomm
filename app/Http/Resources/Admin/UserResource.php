<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'email'             => $this->email,
            'role'              => $this->role,
            'status'            => $this->status,
            'phone'             => $this->phone,
            'photo_url'         => $this->photo_path
                ? asset('storage/' . $this->photo_path)
                : null,
            'email_verified_at' => $this->email_verified_at,
            'last_login_at'     => $this->last_login_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            // Only shown when the record is trashed (soft-deleted).
            'deleted_at'        => $this->when($this->trashed(), $this->deleted_at),
        ];
    }
}
