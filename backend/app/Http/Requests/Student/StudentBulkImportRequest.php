<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentBulkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.first_name' => ['required', 'string'],
            'rows.*.last_name' => ['required', 'string'],
            'rows.*.date_of_birth' => ['required', 'date'],
            'rows.*.gender' => ['required', 'string', 'in:male,female,other,Male,Female,Other'],
            'rows.*.class_name' => ['required', 'string'],
            'rows.*.section_name' => ['required', 'string'],
            'rows.*.guardian_name' => ['required', 'string'],
            'rows.*.guardian_phone' => ['required', 'string'],
            'academic_session_id' => ['nullable', 'uuid', 'exists:academic_sessions,id'],
        ];
    }
}

