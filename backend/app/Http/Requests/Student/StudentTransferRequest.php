<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'application_date' => ['nullable', 'date'],
            'issue_date' => ['nullable', 'date'],
            'destination_school' => ['nullable', 'string', 'max:255'],
            'reason_for_leaving' => ['nullable', 'string', 'max:500'],
            'clearance_status' => ['nullable', 'array'],
        ];
    }
}

