<?php

namespace App\Services\Student;

use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentPromotion;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentProgressionService
{
    /**
     * Promote (or retain) a student into a new academic session.
     * CRITICAL: Preserves historical enrollment records without overwriting.
     */
    public function promoteStudent(
        Student $student,
        string $toSessionId,
        string $toClassId,
        string $toSectionId,
        ?string $rollNumber = null,
        string $status = 'promoted',
        ?string $remarks = null,
        ?string $promotedBy = null
    ): array {
        return DB::transaction(function () use (
            $student,
            $toSessionId,
            $toClassId,
            $toSectionId,
            $rollNumber,
            $status,
            $remarks,
            $promotedBy
        ) {
            // 1. Validate target session exists
            $targetSession = AcademicSession::where('id', $toSessionId)
                ->where('branch_id', $student->branch_id)
                ->first();

            if (!$targetSession) {
                throw new InvalidArgumentException("Target academic session does not exist in this branch.");
            }

            // 2. Validate target class and section
            $targetSection = Section::where('id', $toSectionId)
                ->where('class_id', $toClassId)
                ->first();

            if (!$targetSection) {
                throw new InvalidArgumentException("Target section does not belong to the target class.");
            }

            // 3. Prevent duplicate enrollment in the same target session
            $existingTargetEnrollment = StudentEnrollment::where('student_id', $student->id)
                ->where('academic_session_id', $toSessionId)
                ->first();

            if ($existingTargetEnrollment) {
                throw new InvalidArgumentException("Student is already enrolled in the target academic session.");
            }

            // 4. Retrieve current active enrollment
            $currentEnrollment = $student->currentEnrollment;
            $fromSessionId = $currentEnrollment?->academic_session_id ?? $toSessionId;
            $fromClassId = $currentEnrollment?->class_id ?? $toClassId;

            // 5. Update previous enrollment status to 'promoted' or 'detained' (retaining historical data)
            if ($currentEnrollment) {
                $currentEnrollment->update([
                    'status' => $status,
                ]);
            }

            // 6. Create NEW enrollment for target session
            $newEnrollment = StudentEnrollment::create([
                'tenant_id' => $student->tenant_id,
                'branch_id' => $student->branch_id,
                'student_id' => $student->id,
                'academic_session_id' => $toSessionId,
                'class_id' => $toClassId,
                'section_id' => $toSectionId,
                'roll_number' => $rollNumber,
                'enrollment_date' => now()->toDateString(),
                'status' => 'enrolled',
            ]);

            // 7. Record promotion audit trail
            $promotionAudit = StudentPromotion::create([
                'tenant_id' => $student->tenant_id,
                'branch_id' => $student->branch_id,
                'from_session_id' => $fromSessionId,
                'to_session_id' => $toSessionId,
                'student_id' => $student->id,
                'from_class_id' => $fromClassId,
                'to_class_id' => $toClassId,
                'promotion_status' => $status,
                'remarks' => $remarks ?? "Promoted to {$targetSection->class?->name} Section {$targetSection->name}",
                'promoted_by' => $promotedBy,
            ]);

            return [
                'previous_enrollment' => $currentEnrollment,
                'new_enrollment' => $newEnrollment->load(['schoolClass', 'section', 'academicSession']),
                'promotion' => $promotionAudit,
            ];
        });
    }

    /**
     * Bulk promote a batch of students to the next class and section.
     */
    public function bulkPromote(
        array $studentIds,
        string $toSessionId,
        string $toClassId,
        string $toSectionId,
        ?string $promotedBy = null
    ): array {
        return DB::transaction(function () use (
            $studentIds,
            $toSessionId,
            $toClassId,
            $toSectionId,
            $promotedBy
        ) {
            $promoted = [];
            $skipped = [];

            foreach ($studentIds as $studentId) {
                $student = Student::find($studentId);
                if (!$student) {
                    $skipped[] = ['student_id' => $studentId, 'reason' => 'Student not found'];
                    continue;
                }

                // Check if already in target session
                $exists = StudentEnrollment::where('student_id', $studentId)
                    ->where('academic_session_id', $toSessionId)
                    ->exists();

                if ($exists) {
                    $skipped[] = ['student_id' => $studentId, 'reason' => 'Already enrolled in target session'];
                    continue;
                }

                $result = $this->promoteStudent(
                    $student,
                    $toSessionId,
                    $toClassId,
                    $toSectionId,
                    null,
                    'promoted',
                    'Bulk academic promotion',
                    $promotedBy
                );

                $promoted[] = [
                    'student_id' => $student->id,
                    'admission_number' => $student->admission_number,
                    'name' => $student->full_name,
                ];
            }

            return [
                'promoted_count' => count($promoted),
                'skipped_count' => count($skipped),
                'promoted' => $promoted,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Retrieve complete academic timeline for a student.
     */
    public function getAcademicTimeline(Student $student): array
    {
        $enrollments = StudentEnrollment::where('student_id', $student->id)
            ->with(['academicSession', 'schoolClass', 'section'])
            ->orderBy('enrollment_date', 'asc')
            ->get();

        $promotions = StudentPromotion::where('student_id', $student->id)
            ->with(['fromSession', 'toSession', 'fromClass', 'toClass', 'promotedByUser'])
            ->orderBy('created_at', 'asc')
            ->get();

        $transfers = $student->transfers()
            ->with(['approvedByUser'])
            ->get();

        return [
            'student' => [
                'id' => $student->id,
                'admission_number' => $student->admission_number,
                'full_name' => $student->full_name,
                'status' => $student->status,
            ],
            'enrollment_history' => $enrollments,
            'promotion_history' => $promotions,
            'transfer_history' => $transfers,
        ];
    }
}
