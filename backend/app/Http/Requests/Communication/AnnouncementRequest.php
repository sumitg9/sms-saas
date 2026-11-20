<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('communication.create');
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'target_audience' => 'required|string|in:all,teachers,parents,students,staff',
            'class_id' => 'nullable|uuid|exists:classes,id',
            'section_id' => 'nullable|uuid|exists:sections,id',
            'priority' => 'nullable|string|in:low,normal,urgent',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:published_at',
            'is_published' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'requires_acknowledgement' => 'nullable|boolean',
        ];
    }
}

