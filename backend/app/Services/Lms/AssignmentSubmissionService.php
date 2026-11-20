<?php

namespace App\Services\Lms;

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class AssignmentSubmissionService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Submit assignment online by student or guardian.
     */
    public function submitAssignment(string $homeworkId, array $data, User $user): HomeworkSubmission
    {
        $homework = Homework::findOrFail($homeworkId);

        // Resolve student ID
        $studentId = $data['student_id'] ?? null;
        if (!$studentId) {
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) {
                throw new AuthorizationException('User has no linked student profile for assignment submission.');
            }
            $studentId = $student->id;
        }

        // Validate submission timing against deadline
        $now = Carbon::now();
        $dueDateTime = Carbon::parse($homework->due_date)->endOfDay();
        $isLate = $now->isAfter($dueDateTime);

        $existing = HomeworkSubmission::where('homework_id', $homework->id)
            ->where('student_id', $studentId)
            ->first();

        if ($existing) {
            // Check if allowed to resubmit
            $resubmissionCount = $existing->resubmission_count + 1;

            $existing->update([
                'submission_text' => $data['submission_text'] ?? $existing->submission_text,
                'attachments' => $data['attachments'] ?? $existing->attachments,
                'submitted_at' => $now,
                'is_late' => $isLate,
                'status' => 'submitted',
                'resubmission_count' => $resubmissionCount,
            ]);

            return $existing->fresh(['homework', 'student', 'grader']);
        }

        return HomeworkSubmission::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $homework->branch_id,
            'homework_id' => $homework->id,
            'student_id' => $studentId,
            'submission_text' => $data['submission_text'] ?? null,
            'attachments' => $data['attachments'] ?? [],
            'submitted_at' => $now,
            'is_late' => $isLate,
            'status' => 'submitted',
            'resubmission_count' => 0,
        ]);
    }

    /**
     * Teacher evaluation, marks allocation, and feedback.
     */
    public function gradeSubmission(string $submissionId, array $data, User $grader): HomeworkSubmission
    {
        $submission = HomeworkSubmission::with('homework')->findOrFail($submissionId);
        $maxMarks = (float) ($submission->homework->max_marks ?? 100);

        $marks = isset($data['marks_obtained']) ? (float) $data['marks_obtained'] : null;
        if ($marks !== null) {
            if ($marks < 0 || $marks > $maxMarks) {
                throw new InvalidArgumentException("Marks obtained ({$marks}) must be between 0 and {$maxMarks}.");
            }
        }

        $submission->update([
            'marks_obtained' => $marks,
            'feedback' => $data['feedback'] ?? null,
            'status' => 'graded',
            'graded_by' => $grader->id,
            'graded_at' => Carbon::now(),
        ]);

        return $submission->fresh(['homework', 'student', 'grader']);
    }

    /**
     * Teacher requests resubmission with feedback.
     */
    public function requestResubmission(string $submissionId, string $feedback, User $grader): HomeworkSubmission
    {
        $submission = HomeworkSubmission::findOrFail($submissionId);

        $submission->update([
            'status' => 'resubmission_requested',
            'feedback' => $feedback,
            'graded_by' => $grader->id,
            'graded_at' => Carbon::now(),
        ]);

        return $submission->fresh(['homework', 'student', 'grader']);
    }

    /**
     * List submissions for a specific homework.
     */
    public function listSubmissions(string $homeworkId): Collection
    {
        return HomeworkSubmission::with(['student', 'grader'])
            ->where('homework_id', $homeworkId)
            ->orderBy('submitted_at', 'desc')
            ->get();
    }

    /**
     * Get single submission.
     */
    public function getSubmission(string $submissionId): HomeworkSubmission
    {
        return HomeworkSubmission::with(['homework.schoolClass', 'homework.subject', 'student', 'grader'])
            ->findOrFail($submissionId);
    }
}

