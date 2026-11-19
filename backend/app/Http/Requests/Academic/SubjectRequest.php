<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'in:theory,practical,elective,co_curricular'],
            'credit_hours' => ['nullable', 'numeric', 'min:0.5', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

