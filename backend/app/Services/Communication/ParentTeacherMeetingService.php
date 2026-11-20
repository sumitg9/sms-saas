<?php

namespace App\Services\Communication;

use App\Models\Employee;
use App\Models\Guardian;
use App\Models\PtmMeeting;
use App\Models\Student;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ParentTeacherMeetingService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function list(array $filters = []): Collection
    {
        $query = PtmMeeting::with(['teacher.user', 'guardian.user', 'student'])
            ->orderBy('meeting_date', 'asc')
            ->orderBy('start_time', 'asc');

        if (!empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (!empty($filters['guardian_id'])) {
            $query->where('guardian_id', $filters['guardian_id']);
        }

        if (!empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('meeting_date', '>=', $filters['from_date']);
        }

        return $query->get();
    }

    /**
     * Schedule a meeting (initiated by teacher or staff).
     */
    public function schedule(array $data): PtmMeeting
    {
        $tenantId = $this->tenantContext->getTenantId();
        $branch = $this->tenantContext->getBranch();

        return PtmMeeting::create([
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'] ?? $branch?->id,
            'teacher_id' => $data['teacher_id'],
            'guardian_id' => $data['guardian_id'],
            'student_id' => $data['student_id'],
            'title' => $data['title'],
            'meeting_type' => $data['meeting_type'] ?? 'in_person',
            'meeting_date' => $data['meeting_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'room_number' => $data['room_number'] ?? null,
            'meeting_link' => $data['meeting_link'] ?? null,
            'status' => 'scheduled',
            'requested_by' => 'teacher',
            'teacher_notes' => $data['teacher_notes'] ?? null,
        ]);
    }

    /**
     * Parent requests a meeting with a teacher.
     */
    public function requestMeeting(array $data, User $parentUser): PtmMeeting
    {
        $tenantId = $this->tenantContext->getTenantId();
        $branch = $this->tenantContext->getBranch();

        $guardian = Guardian::where('user_id', $parentUser->id)->firstOrFail();

        // Validate child access
        $child = Student::where('id', $data['student_id'])
            ->whereHas('guardians', fn ($q) => $q->where('guardian_id', $guardian->id))
            ->firstOrFail();

        return PtmMeeting::create([
            'tenant_id' => $tenantId,
            'branch_id' => $child->branch_id ?? $branch?->id,
            'teacher_id' => $data['teacher_id'],
            'guardian_id' => $guardian->id,
            'student_id' => $child->id,
            'title' => $data['title'] ?? "Parent Meeting Request for {$child->first_name}",
            'meeting_type' => $data['meeting_type'] ?? 'in_person',
            'meeting_date' => $data['meeting_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'status' => 'requested',
            'requested_by' => 'parent',
            'parent_notes' => $data['parent_notes'] ?? null,
        ]);
    }

    /**
     * Approve or update meeting status.
     */
    public function updateStatus(PtmMeeting $meeting, string $status, array $notes = []): PtmMeeting
    {
        $validStatuses = ['requested', 'scheduled', 'confirmed', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException("Invalid meeting status: {$status}");
        }

        $meeting->update([
            'status' => $status,
            'teacher_notes' => $notes['teacher_notes'] ?? $meeting->teacher_notes,
            'parent_notes' => $notes['parent_notes'] ?? $meeting->parent_notes,
            'room_number' => $notes['room_number'] ?? $meeting->room_number,
            'meeting_link' => $notes['meeting_link'] ?? $meeting->meeting_link,
        ]);

        return $meeting;
    }

    /**
     * Record feedback & rating after meeting completion.
     */
    public function recordFeedback(PtmMeeting $meeting, string $feedback, ?int $rating = null): PtmMeeting
    {
        $meeting->update([
            'feedback' => $feedback,
            'rating' => $rating,
            'status' => 'completed',
        ]);

        return $meeting;
    }
}

