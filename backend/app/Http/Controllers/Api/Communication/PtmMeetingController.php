<?php

namespace App\Http\Controllers\Api\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\PtmMeetingRequest;
use App\Models\PtmMeeting;
use App\Services\Communication\ParentTeacherMeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PtmMeetingController extends Controller
{
    public function __construct(
        protected ParentTeacherMeetingService $meetingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $meetings = $this->meetingService->list($request->all());

        return response()->json([
            'success' => true,
            'data' => $meetings,
        ]);
    }

    public function schedule(PtmMeetingRequest $request): JsonResponse
    {
        $meeting = $this->meetingService->schedule($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Parent-teacher meeting scheduled successfully.',
            'data' => $meeting,
        ], 201);
    }

    public function requestMeeting(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|uuid|exists:employees,id',
            'student_id' => 'required|uuid|exists:students,id',
            'title' => 'nullable|string|max:200',
            'meeting_type' => 'nullable|string|in:in_person,online',
            'meeting_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'parent_notes' => 'nullable|string',
        ]);

        $meeting = $this->meetingService->requestMeeting($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Meeting request sent to teacher successfully.',
            'data' => $meeting,
        ], 201);
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:requested,scheduled,confirmed,completed,cancelled',
            'teacher_notes' => 'nullable|string',
            'parent_notes' => 'nullable|string',
            'room_number' => 'nullable|string|max:50',
            'meeting_link' => 'nullable|string|url|max:500',
        ]);

        $meeting = PtmMeeting::findOrFail($id);
        $updated = $this->meetingService->updateStatus($meeting, $validated['status'], $validated);

        return response()->json([
            'success' => true,
            'message' => "Meeting status updated to {$validated['status']}.",
            'data' => $updated,
        ]);
    }

    public function feedback(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'feedback' => 'required|string',
            'rating' => 'nullable|integer|between:1,5',
        ]);

        $meeting = PtmMeeting::findOrFail($id);
        $updated = $this->meetingService->recordFeedback($meeting, $validated['feedback'], $validated['rating'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Meeting feedback recorded successfully.',
            'data' => $updated,
        ]);
    }
}

