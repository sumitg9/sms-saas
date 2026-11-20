<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class GradingSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'scheme_type' => ['required', 'string', 'in:letter,gpa,percentage'],
            'description' => ['nullable', 'string'],
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*.grade_name' => ['required', 'string', 'max:20'],
            'ranges.*.min_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'ranges.*.max_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'ranges.*.grade_point' => ['nullable', 'numeric', 'min:0'],
            'ranges.*.description' => ['nullable', 'string', 'max:100'],
        ];
    }
}

