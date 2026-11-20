<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StudentFeeAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid'],
            'enrollment_id' => ['required', 'uuid'],
            'fee_structure_id' => ['required', 'uuid'],
            'concession_id' => ['nullable', 'uuid'],
        ];
    }
}

