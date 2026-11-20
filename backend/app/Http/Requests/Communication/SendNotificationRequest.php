<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('communication.create');
    }

    public function rules(): array
    {
        return [
            'channel' => 'required|string|in:in_app,email,sms,whatsapp,push',
            'recipient' => 'nullable|string|max:150',
            'user_id' => 'nullable|uuid|exists:users,id',
            'template_id' => 'nullable|uuid|exists:notification_templates,id',
            'event_name' => 'nullable|string|max:100',
            'subject' => 'nullable|string|max:200',
            'message' => 'nullable|string',
            'priority' => 'nullable|string|in:low,normal,urgent,emergency',
            'variables' => 'nullable|array',
            'branding' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
            'idempotency_key' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
        ];
    }
}

