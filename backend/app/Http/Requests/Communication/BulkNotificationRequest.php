<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class BulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('communication.create');
    }

    public function rules(): array
    {
        return [
            'channels' => 'required|array|min:1',
            'channels.*' => 'string|in:in_app,email,sms,whatsapp,push',
            'target_audience' => 'required|string|in:all,class,section,role,individual',
            'target_parameters' => 'nullable|array',
            'target_parameters.class_id' => 'nullable|uuid|exists:classes,id',
            'target_parameters.section_id' => 'nullable|uuid|exists:sections,id',
            'target_parameters.role' => 'nullable|string',
            'target_parameters.user_ids' => 'nullable|array',
            'template_id' => 'nullable|uuid|exists:notification_templates,id',
            'event_name' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:200',
            'message' => 'nullable|string',
            'variables' => 'nullable|array',
            'branding' => 'nullable|array',
            'metadata' => 'nullable|array',
        ];
    }
}

