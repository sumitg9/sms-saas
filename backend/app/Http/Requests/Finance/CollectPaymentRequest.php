<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class CollectPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid'],
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:cash,cheque,upi,card,bank_transfer,online'],
            'payment_date' => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'cheque_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'financial_account_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.fee_invoice_id' => ['required_with:allocations', 'uuid'],
            'allocations.*.allocated_amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
            'allocations.*.fee_invoice_item_id' => ['nullable', 'uuid'],
        ];
    }
}

