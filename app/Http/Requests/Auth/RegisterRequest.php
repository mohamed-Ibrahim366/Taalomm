<?php

namespace App\Http\Requests\Auth;

use App\Enums\GradeLevel;
use App\Enums\Governorate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
            // Public registration is limited to students only.
            'role' => ['prohibited'],
            'phone' => ['nullable', 'string', 'max:30'],
            'governorate' => ['required', 'string'],
            'grades' => ['required', 'string', 'in:' . implode(',', GradeLevel::values())],
        ];
    }
}
