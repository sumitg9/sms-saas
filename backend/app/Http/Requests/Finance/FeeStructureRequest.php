<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class FeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_session_id' => ['required', 'uuid'],
            'fee_category_id' => ['required', 'uuid'],
            'class_id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'frequency' => ['required', 'string', 'in:annual,term,monthly,one_time'],
            'due_date' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_head_name' => ['required', 'string', 'max:100'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.is_mandatory' => ['nullable', 'boolean'],
        ];
    }
}

