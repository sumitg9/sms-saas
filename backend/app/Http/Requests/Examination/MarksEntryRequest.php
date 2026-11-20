<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class MarksEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.enrollment_id' => ['required', 'uuid'],
            'marks.*.marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'marks.*.is_absent' => ['nullable', 'boolean'],
            'marks.*.remarks' => ['nullable', 'string', 'max:255'],
            'marks.*.components' => ['nullable', 'array'],
            'marks.*.components.*.component_id' => ['required_with:marks.*.components', 'uuid'],
            'marks.*.components.*.marks_obtained' => ['required_with:marks.*.components', 'numeric', 'min:0'],
        ];
    }
}

