<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Must be globally unique in users table.
                Rule::unique('users', 'email'),
                // Must be different from the user's current email.
                Rule::notIn([$this->user()->email]),
            ],
            // Require the user to confirm their current password for security.
            'password' => ['required', 'string', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.not_in'              => 'The new email must be different from your current email address.',
            'password.current_password' => 'The provided password is incorrect.',
        ];
    }
}
