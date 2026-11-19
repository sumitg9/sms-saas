<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class TimetableSubstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'substitute_employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

