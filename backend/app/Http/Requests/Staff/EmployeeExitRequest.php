<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exit_type' => ['required', 'in:resignation,termination,retirement,contract_end'],
            'notice_date' => ['required', 'date'],
            'last_working_day' => ['required', 'date', 'after_or_equal:notice_date'],
            'reason' => ['required', 'string'],
            'clearance_status' => ['nullable', 'in:pending,in_progress,completed'],
            'settlement_status' => ['nullable', 'in:pending,cleared'],
            'clearance_checklist' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}

