<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'applicant_first_name' => ['required', 'string', 'max:100'],
            'applicant_last_name' => ['required', 'string', 'max:100'],
            'applied_class_id' => ['required', 'uuid', 'exists:classes,id'],
            'academic_session_id' => ['nullable', 'uuid', 'exists:academic_sessions,id'],
            'date_of_application' => ['nullable', 'date'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'caste_category' => ['nullable', 'string', 'max:50'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'previous_grade' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'parent_name' => ['nullable', 'string', 'max:150'],
            'parent_phone' => ['nullable', 'string', 'max:50'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_relation' => ['nullable', 'string', 'in:father,mother,legal_guardian,other'],
            'parent_occupation' => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'application_fee_paid' => ['nullable', 'boolean'],
        ];
    }
}

