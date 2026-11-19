<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_session_id' => ['required', 'uuid', 'exists:academic_sessions,id'],
            'to_class_id' => ['required', 'uuid', 'exists:classes,id'],
            'to_section_id' => ['required', 'uuid', 'exists:sections,id'],
            'roll_number' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'in:promoted,detained,double_promoted'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}

