<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'max_capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

