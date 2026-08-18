<?php

namespace App\Http\Requests\Course;

use App\Enums\CourseLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
public function rules(): array
{
    return [

        'category_id'=>[
            'required',
            'exists:categories,id'
        ],

        'title'=>[
            'required',
            'string',
            'max:255'
        ],

        'slug'=>[
            'nullable',
            'string',
            'max:255',
            'unique:courses,slug'
        ],

        'description'=>[
            'required',
            'string'
        ],

        'thumbnail'=>[
            'nullable',
            'image',
            'max:4096'
        ],

        'price'=>[
            'required',
            'numeric',
            'min:0'
        ],

        'currency'=>[
            'required',
            'string',
            'max:10'
        ],

        'duration'=>[
            'required',
            'integer',
            'min:1'
        ],

        'level'=>[
            'required',
            Rule::enum(CourseLevel::class)
        ],

        'is_featured'=>[
            'boolean'
        ],

        'is_published'=>[
            'boolean'
        ],

    ];
}

}
