<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via Policy in the controller.
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            // Admin endpoint may only create teacher accounts.
            // The admin account itself is provisioned manually on the server.
            'role'     => ['required', 'in:' . UserRole::TEACHER->value],
            'phone'    => ['nullable', 'string', 'max:30'],
        ];
    }
}
