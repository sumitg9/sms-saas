<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;

class PtmMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('communication.create');
    }

    public function rules(): array
    {
        return [
            'teacher_id' => 'required|uuid|exists:employees,id',
            'guardian_id' => 'nullable|uuid|exists:guardians,id',
            'student_id' => 'required|uuid|exists:students,id',
            'title' => 'nullable|string|max:200',
            'meeting_type' => 'nullable|string|in:in_person,online',
            'meeting_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room_number' => 'nullable|string|max:50',
            'meeting_link' => 'nullable|string|url|max:500',
            'parent_notes' => 'nullable|string',
            'teacher_notes' => 'nullable|string',
        ];
    }
}

