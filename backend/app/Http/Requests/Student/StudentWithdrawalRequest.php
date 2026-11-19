<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class StudentWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exit_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
