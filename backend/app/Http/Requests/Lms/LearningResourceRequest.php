<?php

namespace App\Http\Requests\Lms;

use Illuminate\Foundation\Http\FormRequest;

class LearningResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'uuid', 'exists:classes,id'],
            'section_id' => ['nullable', 'uuid', 'exists:sections,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'resource_type' => ['required', 'string', 'in:document,notes,video,link'],
            'file_url' => ['nullable', 'string', 'max:500'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'external_url' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }
}

