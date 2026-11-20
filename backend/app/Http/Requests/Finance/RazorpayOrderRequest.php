<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class RazorpayOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'student_id' => ['required', 'uuid'],
            'currency' => ['nullable', 'string', 'size:3'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['uuid'],
        ];
    }
}

