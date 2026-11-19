<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class AdmissionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:applied,under_review,waitlisted,approved,rejected,enrolled'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'waitlist_number' => ['nullable', 'integer', 'min:1'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

