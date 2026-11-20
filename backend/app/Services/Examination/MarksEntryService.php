<?php

namespace App\Services\Examination;

use App\Models\AssessmentComponent;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\ExamSchedule;
use App\Models\ResultCorrection;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentEnrollment;
use App\Models\StudentMark;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarksEntryService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Verify that the user has permission to enter marks for this class and subject schedule.
     */
    public function validateTeacherAccess(User $user, ExamSchedule $schedule): void
    {
        // Platform Super Admin, School Owner, School Admin, and Principal have global examination access
        if ($user->hasAnyRole(['platform_admin', 'school_owner', 'school_admin', 'principal'])) {
            return;
        }

        // Check if teacher is assigned to this specific class and subject
        $employee = Employee::where('user_id', $user->id)->first();
        if (!$employee) {
            throw new AuthorizationException('User has no active staff or teacher profile.');
        }

        $isAssigned = TeacherSubject::where('employee_id', $employee->id)
            ->whereHas('classSubject', function ($query) use ($schedule) {
                $query->where('class_id', $schedule->class_id)
                    ->where('subject_id', $schedule->subject_id);
            })
            ->exists();

        if (!$isAssigned) {
            throw new AuthorizationException('You are not authorized to enter marks for this class and subject.');
        }
    }

    /**
     * Enter or bulk enter student marks for an exam schedule.
     */
    public function enterStudentMarks(ExamSchedule $schedule, array $marksData, User $user): array
    {
        $this->validateTeacherAccess($user, $schedule);

        return DB::transaction(function () use ($schedule, $marksData, $user) {
            $tenantId = $this->tenantContext->getTenantId();
            $branchId = $schedule->examination->branch_id ?? $this->tenantContext->getBranchId();
            $maxMarks = (float) $schedule->max_marks;
            $savedMarks = [];

            foreach ($marksData as $entry) {
                $enrollmentId = $entry['enrollment_id'];
                $enrollment = StudentEnrollment::findOrFail($enrollmentId);
                $studentId = $entry['student_id'] ?? $enrollment->student_id;
                $isAbsent = !empty($entry['is_absent']);
                $marksObtained = $isAbsent ? 0.00 : (isset($entry['marks_obtained']) ? round((float) $entry['marks_obtained'], 2) : null);

                if (!$isAbsent && $marksObtained !== null) {
                    if ($marksObtained < 0) {
                        throw new InvalidArgumentException("Marks obtained cannot be negative for student {$studentId}.");
                    }
                    if ($marksObtained > $maxMarks) {
                        throw new InvalidArgumentException("Marks obtained ({$marksObtained}) exceeds maximum marks ({$maxMarks}).");
                    }
                }

                $mark = StudentMark::updateOrCreate(
                    [
                        'exam_schedule_id' => $schedule->id,
                        'enrollment_id' => $enrollmentId,
                    ],
                    [
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'student_id' => $studentId,
                        'marks_obtained' => $marksObtained,
                        'is_absent' => $isAbsent,
                        'remarks' => $entry['remarks'] ?? null,
                        'entered_by' => $user->id,
                    ]
                );

                // Process sub-component assessments (e.g. Theory, Practical, Viva)
                if (!empty($entry['components']) && is_array($entry['components'])) {
                    foreach ($entry['components'] as $comp) {
                        $component = AssessmentComponent::findOrFail($comp['component_id']);
                        $compMarks = round((float) $comp['marks_obtained'], 2);

                        if ($compMarks < 0 || $compMarks > (float) $component->max_marks) {
                            throw new InvalidArgumentException("Component marks for {$component->component_name} must be between 0 and {$component->max_marks}.");
                        }

                        StudentAssessment::updateOrCreate(
                            [
                                'student_marks_id' => $mark->id,
                                'component_id' => $component->id,
                            ],
                            [
                                'tenant_id' => $tenantId,
                                'marks_obtained' => $compMarks,
                            ]
                        );
                    }
                }

                $savedMarks[] = $mark;
            }

            return $savedMarks;
        });
    }

    /**
     * Request a marks correction with audit reason.
     */
    public function requestCorrection(string $markId, float $newMarks, string $reason, User $user): ResultCorrection
    {
        $mark = StudentMark::with('schedule')->findOrFail($markId);
        $maxMarks = (float) $mark->schedule->max_marks;

        $newMarks = round($newMarks, 2);
        if ($newMarks < 0 || $newMarks > $maxMarks) {
            throw new InvalidArgumentException("Correction marks ({$newMarks}) must be between 0 and {$maxMarks}.");
        }

        return ResultCorrection::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'student_marks_id' => $mark->id,
            'old_marks' => $mark->marks_obtained,
            'new_marks' => $newMarks,
            'reason' => $reason,
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Approve or reject a marks correction request.
     */
    public function reviewCorrection(string $correctionId, bool $approve, User $approver): ResultCorrection
    {
        return DB::transaction(function () use ($correctionId, $approve, $approver) {
            $correction = ResultCorrection::with('studentMark')->findOrFail($correctionId);

            if ($correction->status !== 'pending') {
                throw new InvalidArgumentException('This correction request has already been processed.');
            }

            if ($approve) {
                $mark = $correction->studentMark;
                $mark->update([
                    'marks_obtained' => $correction->new_marks,
                    'is_absent' => false,
                ]);
                $correction->update([
                    'status' => 'approved',
                    'approved_by' => $approver->id,
                ]);
            } else {
                $correction->update([
                    'status' => 'rejected',
                    'approved_by' => $approver->id,
                ]);
            }

            return $correction->fresh(['studentMark', 'requester', 'approver']);
        });
    }

    /**
     * Generate CSV template for bulk marks entry for an exam schedule.
     */
    public function generateMarksCsvTemplate(ExamSchedule $schedule): string
    {
        $enrollments = StudentEnrollment::with('student')
            ->where('class_id', $schedule->class_id)
            ->where('status', 'active')
            ->orderBy('roll_number')
            ->get();

        $rows = [];
        $rows[] = ['enrollment_id', 'student_id', 'admission_number', 'student_name', 'roll_number', 'marks_obtained', 'is_absent', 'remarks'];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $rows[] = [
                $enrollment->id,
                $student->id,
                $student->admission_number,
                $student->first_name . ' ' . $student->last_name,
                $enrollment->roll_number ?? '',
                '', // marks empty for filling
                '0',
                '',
            ];
        }

        $fp = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    /**
     * Bulk import marks from CSV content.
     */
    public function importMarksFromCsv(ExamSchedule $schedule, string $csvContent, User $user): array
    {
        $lines = explode("\n", trim($csvContent));
        if (count($lines) < 2) {
            throw new InvalidArgumentException('CSV contains no data rows.');
        }

        $header = str_getcsv(array_shift($lines));
        $enrollmentIdIdx = array_search('enrollment_id', $header);
        $marksIdx = array_search('marks_obtained', $header);
        $absentIdx = array_search('is_absent', $header);
        $remarksIdx = array_search('remarks', $header);

        if ($enrollmentIdIdx === false || $marksIdx === false) {
            throw new InvalidArgumentException('Missing required headers enrollment_id and marks_obtained in CSV.');
        }

        $marksData = [];
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            $enrollmentId = trim($row[$enrollmentIdIdx] ?? '');
            $marks = trim($row[$marksIdx] ?? '');
            $isAbsent = ($absentIdx !== false && trim($row[$absentIdx] ?? '') === '1');
            $remarks = ($remarksIdx !== false) ? trim($row[$remarksIdx] ?? '') : null;

            if (!empty($enrollmentId)) {
                $marksData[] = [
                    'enrollment_id' => $enrollmentId,
                    'marks_obtained' => is_numeric($marks) ? (float) $marks : null,
                    'is_absent' => $isAbsent,
                    'remarks' => $remarks,
                ];
            }
        }

        return $this->enterStudentMarks($schedule, $marksData, $user);
    }
}

