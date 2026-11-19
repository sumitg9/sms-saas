<?php

namespace App\Services\Student;

use App\Models\AcademicSession;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAdmission;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AdmissionService
{
    /**
     * Create an online/in-person admission application.
     */
    public function createApplication(array $data, string $tenantId, string $branchId, string $sessionId): StudentAdmission
    {
        $applicationNumber = $data['application_number'] ?? $this->generateApplicationNumber($branchId);

        // Verify applied class exists and belongs to branch
        $class = SchoolClass::where('id', $data['applied_class_id'])
            ->where('branch_id', $branchId)
            ->first();

        if (!$class) {
            throw new InvalidArgumentException("Applied class does not exist in this branch.");
        }

        $inquiryDetails = [
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'blood_group' => $data['blood_group'] ?? null,
            'nationality' => $data['nationality'] ?? 'Indian',
            'caste_category' => $data['caste_category'] ?? 'General',
            'previous_school' => $data['previous_school'] ?? null,
            'previous_grade' => $data['previous_grade'] ?? null,
            'address' => $data['address'] ?? null,
            'parent_name' => $data['parent_name'] ?? null,
            'parent_phone' => $data['parent_phone'] ?? null,
            'parent_email' => $data['parent_email'] ?? null,
            'parent_relation' => $data['parent_relation'] ?? 'father',
            'parent_occupation' => $data['parent_occupation'] ?? null,
            'emergency_contact' => $data['emergency_contact'] ?? null,
            'notes' => $data['notes'] ?? null,
            'documents' => $data['documents'] ?? [],
            'status_history' => [
                [
                    'status' => 'applied',
                    'timestamp' => now()->toIso8601String(),
                    'remarks' => 'Application submitted successfully',
                ],
            ],
        ];

        return StudentAdmission::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'academic_session_id' => $sessionId,
            'application_number' => $applicationNumber,
            'applicant_first_name' => $data['applicant_first_name'],
            'applicant_last_name' => $data['applicant_last_name'],
            'applied_class_id' => $class->id,
            'date_of_application' => $data['date_of_application'] ?? now()->toDateString(),
            'status' => 'applied',
            'application_fee_paid' => $data['application_fee_paid'] ?? false,
            'inquiry_details' => $inquiryDetails,
        ]);
    }

    /**
     * Update application status (review, waitlist, approve, reject).
     */
    public function updateStatus(
        StudentAdmission $admission,
        string $status,
        ?string $remarks = null,
        ?int $waitlistNumber = null,
        ?string $rejectionReason = null
    ): StudentAdmission {
        $allowedStatuses = ['applied', 'under_review', 'waitlisted', 'approved', 'rejected', 'enrolled'];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Invalid admission status: {$status}");
        }

        $inquiry = $admission->inquiry_details ?? [];
        $history = $inquiry['status_history'] ?? [];

        $history[] = [
            'from' => $admission->status,
            'to' => $status,
            'timestamp' => now()->toIso8601String(),
            'remarks' => $remarks,
            'rejection_reason' => $rejectionReason,
            'waitlist_number' => $waitlistNumber,
        ];

        $inquiry['status_history'] = $history;

        if ($waitlistNumber !== null) {
            $inquiry['waitlist_number'] = $waitlistNumber;
        }

        if ($rejectionReason !== null) {
            $inquiry['rejection_reason'] = $rejectionReason;
        }

        $admission->update([
            'status' => $status,
            'inquiry_details' => $inquiry,
        ]);

        return $admission->fresh();
    }

    /**
     * Confirm admission: creates Student demographic master, links Guardian,
     * creates initial StudentEnrollment, and marks application as enrolled.
     */
    public function confirmAdmission(
        StudentAdmission $admission,
        array $enrollmentData,
        string $schoolId
    ): array {
        if ($admission->status === 'enrolled') {
            throw new InvalidArgumentException("This admission application is already enrolled.");
        }

        return DB::transaction(function () use ($admission, $enrollmentData, $schoolId) {
            $branchId = $admission->branch_id;
            $tenantId = $admission->tenant_id;
            $sessionId = $admission->academic_session_id;

            $inquiry = $admission->inquiry_details ?? [];

            // 1. Generate or use supplied admission number
            $admissionNumber = $enrollmentData['admission_number']
                ?? $this->generateAdmissionNumber($branchId);

            // 2. Validate section
            $section = Section::where('id', $enrollmentData['section_id'])
                ->where('class_id', $admission->applied_class_id)
                ->first();

            if (!$section) {
                throw new InvalidArgumentException("Section does not belong to the applied class.");
            }

            // 3. Create Student demographic record
            $student = Student::create([
                'tenant_id' => $tenantId,
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'admission_number' => $admissionNumber,
                'admission_date' => $enrollmentData['admission_date'] ?? now()->toDateString(),
                'first_name' => $admission->applicant_first_name,
                'middle_name' => $enrollmentData['middle_name'] ?? null,
                'last_name' => $admission->applicant_last_name,
                'gender' => $enrollmentData['gender'] ?? $inquiry['gender'] ?? 'other',
                'date_of_birth' => $enrollmentData['date_of_birth'] ?? $inquiry['date_of_birth'] ?? now()->subYears(6)->toDateString(),
                'blood_group' => $enrollmentData['blood_group'] ?? $inquiry['blood_group'] ?? null,
                'nationality' => $enrollmentData['nationality'] ?? $inquiry['nationality'] ?? 'Indian',
                'caste_category' => $enrollmentData['caste_category'] ?? $inquiry['caste_category'] ?? 'General',
                'national_id' => $enrollmentData['national_id'] ?? null,
                'emergency_contact_name' => $enrollmentData['emergency_contact_name'] ?? $inquiry['emergency_contact'] ?? $inquiry['parent_name'] ?? null,
                'emergency_contact_phone' => $enrollmentData['emergency_contact_phone'] ?? $inquiry['parent_phone'] ?? null,
                'current_address' => $enrollmentData['current_address'] ?? $inquiry['address'] ?? null,
                'permanent_address' => $enrollmentData['permanent_address'] ?? $inquiry['address'] ?? null,
                'status' => 'active',
            ]);

            // 4. Link or create Parent / Guardian
            $guardianPhone = $enrollmentData['guardian_phone'] ?? $inquiry['parent_phone'] ?? null;
            $guardianName = $enrollmentData['guardian_name'] ?? $inquiry['parent_name'] ?? null;

            if ($guardianPhone && $guardianName) {
                $nameParts = explode(' ', trim($guardianName), 2);
                $gFirst = $nameParts[0];
                $gLast = $nameParts[1] ?? 'Guardian';

                // Look up existing guardian in tenant by phone
                $guardian = Guardian::where('tenant_id', $tenantId)
                    ->where('phone', $guardianPhone)
                    ->first();

                if (!$guardian) {
                    $guardian = Guardian::create([
                        'tenant_id' => $tenantId,
                        'first_name' => $gFirst,
                        'last_name' => $gLast,
                        'relation_type' => $enrollmentData['guardian_relation'] ?? $inquiry['parent_relation'] ?? 'father',
                        'phone' => $guardianPhone,
                        'email' => $enrollmentData['guardian_email'] ?? $inquiry['parent_email'] ?? null,
                        'occupation' => $inquiry['parent_occupation'] ?? null,
                        'address' => $enrollmentData['current_address'] ?? $inquiry['address'] ?? null,
                    ]);
                }

                // Link to StudentGuardian
                StudentGuardian::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'student_id' => $student->id,
                        'guardian_id' => $guardian->id,
                    ],
                    [
                        'is_primary' => true,
                        'has_pickup_rights' => true,
                        'receives_invoices' => true,
                        'emergency_contact_priority' => 1,
                    ]
                );
            }

            // 5. Create initial Student Enrollment (preserving academic progression)
            $enrollment = StudentEnrollment::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'student_id' => $student->id,
                'academic_session_id' => $sessionId,
                'class_id' => $admission->applied_class_id,
                'section_id' => $section->id,
                'roll_number' => $enrollmentData['roll_number'] ?? null,
                'enrollment_date' => $enrollmentData['admission_date'] ?? now()->toDateString(),
                'status' => 'enrolled',
            ]);

            // 6. Update Admission record
            $this->updateStatus($admission, 'enrolled', 'Admission confirmed and enrolled into class ' . $admission->applied_class_id);
            $admission->update(['student_id' => $student->id]);

            return [
                'student' => $student->load(['guardians', 'currentEnrollment']),
                'enrollment' => $enrollment,
                'admission' => $admission,
            ];
        });
    }

    /**
     * Generate unique application number for the branch.
     * Format: ADM-{YEAR}-{0001}
     */
    public function generateApplicationNumber(string $branchId, ?string $year = null): string
    {
        $year = $year ?? date('Y');
        $prefix = "ADM-{$year}-";

        $lastNumber = StudentAdmission::where('branch_id', $branchId)
            ->where('application_number', 'LIKE', "{$prefix}%")
            ->orderByDesc('application_number')
            ->value('application_number');

        if ($lastNumber && preg_match('/-(\d+)$/', $lastNumber, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        } else {
            $nextSeq = 1;
        }

        return sprintf("%s%04d", $prefix, $nextSeq);
    }

    /**
     * Generate unique admission number for the branch.
     * Format: STU-{YEAR}-{0001}
     */
    public function generateAdmissionNumber(string $branchId, ?string $year = null): string
    {
        $year = $year ?? date('Y');
        $prefix = "STU-{$year}-";

        $lastNumber = Student::where('branch_id', $branchId)
            ->where('admission_number', 'LIKE', "{$prefix}%")
            ->orderByDesc('admission_number')
            ->value('admission_number');

        if ($lastNumber && preg_match('/-(\d+)$/', $lastNumber, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        } else {
            $nextSeq = 1;
        }

        return sprintf("%s%04d", $prefix, $nextSeq);
    }
}
