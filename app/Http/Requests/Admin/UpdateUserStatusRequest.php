<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled via Policy in the controller.
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                // 'pending' is an automatic initial state — admin cannot set it manually.
                Rule::in([
                    UserStatus::ACTIVE->value,
                    UserStatus::INACTIVE->value,
                    UserStatus::SUSPENDED->value,
                ]),
            ],
        ];
    }
}
