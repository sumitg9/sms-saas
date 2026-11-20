<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class PublishResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'uuid'],
            'allow_parent_view' => ['nullable', 'boolean'],
            'allow_student_view' => ['nullable', 'boolean'],
        ];
    }
}

