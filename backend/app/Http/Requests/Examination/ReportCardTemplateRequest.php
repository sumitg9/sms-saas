<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class ReportCardTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'template_schema' => ['nullable', 'array'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

