<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class ExamScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'examination_id' => ['required', 'uuid'],
            'class_id' => ['required', 'uuid'],
            'subject_id' => ['required', 'uuid'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required'],
            'end_time' => ['required'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'max_marks' => ['required', 'numeric', 'min:1'],
            'pass_marks' => ['required', 'numeric', 'min:0'],
            'max_theory_marks' => ['nullable', 'numeric', 'min:0'],
            'max_practical_marks' => ['nullable', 'numeric', 'min:0'],
            'pass_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'components' => ['nullable', 'array'],
            'components.*.component_name' => ['required_with:components', 'string', 'max:100'],
            'components.*.max_marks' => ['required_with:components', 'numeric', 'min:0'],
            'components.*.pass_marks' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

