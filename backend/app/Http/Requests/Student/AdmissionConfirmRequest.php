<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class AdmissionConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'uuid', 'exists:sections,id'],
            'admission_number' => ['nullable', 'string', 'max:50'],
            'admission_date' => ['nullable', 'date'],
            'roll_number' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:50'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'guardian_relation' => ['nullable', 'string', 'in:father,mother,legal_guardian,other'],
            'current_address' => ['nullable', 'string'],
        ];
    }
}

