<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class TimetableSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'uuid', 'exists:timetable_periods,id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'class_id' => ['required', 'uuid', 'exists:classes,id'],
            'section_id' => ['required', 'uuid', 'exists:sections,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'room_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}

