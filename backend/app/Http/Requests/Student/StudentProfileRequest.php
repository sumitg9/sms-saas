<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPost = $this->isMethod('post');

        return [
            'first_name' => [$isPost ? 'required' : 'sometimes', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => [$isPost ? 'required' : 'sometimes', 'string', 'max:100'],
            'admission_number' => ['nullable', 'string', 'max:50'],
            'admission_date' => ['nullable', 'date'],
            'date_of_birth' => [$isPost ? 'required' : 'sometimes', 'date'],
            'gender' => [$isPost ? 'required' : 'sometimes', 'string', 'in:male,female,other'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'caste_category' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'photo_url' => ['nullable', 'string', 'max:500'],
            'current_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive,transferred,withdrawn,graduated'],
            
            // Enrollment fields on creation
            'class_id' => [$isPost ? 'required' : 'nullable', 'uuid', 'exists:classes,id'],
            'section_id' => [$isPost ? 'required' : 'nullable', 'uuid', 'exists:sections,id'],
            'academic_session_id' => ['nullable', 'uuid', 'exists:academic_sessions,id'],
            'roll_number' => ['nullable', 'string', 'max:30'],

            // Guardian fields on creation
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_relation' => ['nullable', 'string', 'in:father,mother,legal_guardian,other'],
            'guardian_occupation' => ['nullable', 'string', 'max:100'],

            // Medical fields
            'allergies' => ['nullable', 'array'],
            'chronic_conditions' => ['nullable', 'string'],
            'medications' => ['nullable', 'string'],
            'emergency_notes' => ['nullable', 'string'],
            'doctor_name' => ['nullable', 'string', 'max:150'],
            'doctor_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
