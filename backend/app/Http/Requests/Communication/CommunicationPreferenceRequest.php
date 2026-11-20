<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class CommunicationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Users can manage their own communication preferences
    }

    public function rules(): array
    {
        return [
            'email_enabled' => 'nullable|boolean',
            'sms_enabled' => 'nullable|boolean',
            'whatsapp_enabled' => 'nullable|boolean',
            'push_enabled' => 'nullable|boolean',
            'emergency_override' => 'nullable|boolean',
            'dnd_start_time' => 'nullable|string|date_format:H:i',
            'dnd_end_time' => 'nullable|string|date_format:H:i',
        ];
    }
}

