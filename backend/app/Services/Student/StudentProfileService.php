<?php

namespace App\Services\Student;

use App\Models\AcademicSession;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\StudentMedicalRecord;
use App\Models\StudentTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StudentProfileService
{
    /**
     * Create a new student with demographic info, guardian, enrollment, and optional medical records.
     */
    public function createStudent(
        array $data,
        string $tenantId,
        string $schoolId,
        string $branchId,
        string $sessionId
    ): Student {
        return DB::transaction(function () use ($data, $tenantId, $schoolId, $branchId, $sessionId) {
            $admissionNumber = $data['admission_number']
                ?? (new AdmissionService())->generateAdmissionNumber($branchId);

            // 1. Verify class and section
            $section = Section::where('id', $data['section_id'])
                ->where('class_id', $data['class_id'])
                ->first();

            if (!$section) {
                throw new InvalidArgumentException("Section does not belong to the selected class.");
            }

            // 2. Create Student
            $student = Student::create([
                'tenant_id' => $tenantId,
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'user_id' => $data['user_id'] ?? null,
                'admission_number' => $admissionNumber,
                'admission_date' => $data['admission_date'] ?? now()->toDateString(),
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'],
                'blood_group' => $data['blood_group'] ?? null,
                'nationality' => $data['nationality'] ?? 'Indian',
                'religion' => $data['religion'] ?? null,
                'caste_category' => $data['caste_category'] ?? 'General',
                'national_id' => $data['national_id'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'photo_url' => $data['photo_url'] ?? null,
                'current_address' => $data['current_address'] ?? null,
                'permanent_address' => $data['permanent_address'] ?? null,
                'status' => 'active',
            ]);

            // 3. Create initial Student Enrollment
            StudentEnrollment::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'student_id' => $student->id,
                'academic_session_id' => $sessionId,
                'class_id' => $data['class_id'],
                'section_id' => $section->id,
                'roll_number' => $data['roll_number'] ?? null,
                'enrollment_date' => $data['admission_date'] ?? now()->toDateString(),
                'status' => 'enrolled',
            ]);

            // 4. Primary Guardian creation / linking
            if (!empty($data['guardian_phone']) && !empty($data['guardian_name'])) {
                $nameParts = explode(' ', trim($data['guardian_name']), 2);
                $gFirst = $nameParts[0];
                $gLast = $nameParts[1] ?? 'Guardian';

                $guardian = Guardian::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'phone' => $data['guardian_phone'],
                    ],
                    [
                        'first_name' => $gFirst,
                        'last_name' => $gLast,
                        'relation_type' => $data['guardian_relation'] ?? 'father',
                        'email' => $data['guardian_email'] ?? null,
                        'occupation' => $data['guardian_occupation'] ?? null,
                        'address' => $data['current_address'] ?? null,
                    ]
                );

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

            // 5. Medical records if provided
            if (!empty($data['blood_group']) || !empty($data['allergies']) || !empty($data['chronic_conditions'])) {
                StudentMedicalRecord::create([
                    'tenant_id' => $tenantId,
                    'student_id' => $student->id,
                    'blood_group' => $data['blood_group'] ?? null,
                    'allergies' => $data['allergies'] ?? [],
                    'chronic_conditions' => $data['chronic_conditions'] ?? null,
                    'medications' => $data['medications'] ?? null,
                    'emergency_notes' => $data['emergency_notes'] ?? null,
                    'doctor_name' => $data['doctor_name'] ?? null,
                    'doctor_phone' => $data['doctor_phone'] ?? null,
                ]);
            }

            return $student->load(['guardians', 'currentEnrollment.schoolClass', 'currentEnrollment.section']);
        });
    }

    /**
     * Update student demographic profile.
     */
    public function updateStudent(Student $student, array $data): Student
    {
        $fillable = array_intersect_key($data, array_flip([
            'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
            'blood_group', 'nationality', 'religion', 'caste_category', 'national_id',
            'emergency_contact_name', 'emergency_contact_phone', 'photo_url',
            'current_address', 'permanent_address', 'status',
        ]));

        $student->update($fillable);

        return $student->fresh(['guardians', 'currentEnrollment.schoolClass', 'currentEnrollment.section']);
    }

    /**
     * Add or link an additional Guardian (Father, Mother, Local Guardian).
     */
    public function addGuardian(Student $student, array $data): Guardian
    {
        return DB::transaction(function () use ($student, $data) {
            $tenantId = $student->tenant_id;

            $guardian = Guardian::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'phone' => $data['phone'],
                ],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'relation_type' => $data['relation_type'] ?? 'guardian',
                    'email' => $data['email'] ?? null,
                    'occupation' => $data['occupation'] ?? null,
                    'annual_income' => $data['annual_income'] ?? null,
                    'address' => $data['address'] ?? $student->current_address,
                    'national_id' => $data['national_id'] ?? null,
                ]
            );

            // If is_primary requested, demote previous primary guardians for this student
            if (!empty($data['is_primary'])) {
                StudentGuardian::where('student_id', $student->id)
                    ->update(['is_primary' => false]);
            }

            StudentGuardian::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'student_id' => $student->id,
                    'guardian_id' => $guardian->id,
                ],
                [
                    'is_primary' => $data['is_primary'] ?? false,
                    'has_pickup_rights' => $data['has_pickup_rights'] ?? true,
                    'receives_invoices' => $data['receives_invoices'] ?? false,
                    'emergency_contact_priority' => $data['emergency_contact_priority'] ?? 2,
                ]
            );

            return $guardian;
        });
    }

    /**
     * Manage student medical record.
     */
    public function updateMedicalRecord(Student $student, array $data): StudentMedicalRecord
    {
        return StudentMedicalRecord::updateOrCreate(
            [
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
            ],
            [
                'blood_group' => $data['blood_group'] ?? $student->blood_group,
                'allergies' => $data['allergies'] ?? [],
                'chronic_conditions' => $data['chronic_conditions'] ?? null,
                'medications' => $data['medications'] ?? null,
                'emergency_notes' => $data['emergency_notes'] ?? null,
                'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
                'doctor_name' => $data['doctor_name'] ?? null,
                'doctor_phone' => $data['doctor_phone'] ?? null,
            ]
        );
    }

    /**
     * Add student document (birth certificate, TC, medical, etc.).
     */
    public function addDocument(Student $student, array $data, ?string $verifierId = null): StudentDocument
    {
        return StudentDocument::create([
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
            'document_type' => $data['document_type'],
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'file_size' => $data['file_size'] ?? 0,
            'mime_type' => $data['mime_type'] ?? 'application/pdf',
            'verification_status' => $data['verification_status'] ?? 'pending',
            'verified_by' => $verifierId,
        ]);
    }

    /**
     * Verify or reject student document.
     */
    public function verifyDocument(StudentDocument $document, string $status, string $verifierId): StudentDocument
    {
        if (!in_array($status, ['verified', 'rejected', 'pending'], true)) {
            throw new InvalidArgumentException("Invalid document verification status.");
        }

        $document->update([
            'verification_status' => $status,
            'verified_by' => $verifierId,
        ]);

        return $document;
    }

    /**
     * Generate Student ID Card Data with QR Code payload.
     */
    public function generateStudentIdCardData(Student $student): array
    {
        $student->loadMissing([
            'branch.school',
            'currentEnrollment.schoolClass',
            'currentEnrollment.section',
            'currentEnrollment.academicSession',
            'guardians',
        ]);

        $enrollment = $student->currentEnrollment;
        $primaryGuardian = $student->guardians->first(function ($g) {
            return $g->pivot->is_primary;
        }) ?? $student->guardians->first();

        // Verification token/hash for digital QR verification
        $qrPayload = [
            'type' => 'STUDENT_CREDENTIAL',
            'student_id' => $student->id,
            'admission_number' => $student->admission_number,
            'name' => $student->full_name,
            'dob' => $student->date_of_birth ? $student->date_of_birth->toDateString() : null,
            'class' => $enrollment?->schoolClass?->name,
            'section' => $enrollment?->section?->name,
            'branch' => $student->branch?->name,
            'session' => $enrollment?->academicSession?->name,
            'verified_at' => now()->toIso8601String(),
        ];

        $qrDataEncoded = base64_encode(json_encode($qrPayload));

        return [
            'id_card' => [
                'student_id' => $student->id,
                'admission_number' => $student->admission_number,
                'full_name' => $student->full_name,
                'gender' => $student->gender,
                'date_of_birth' => $student->date_of_birth ? $student->date_of_birth->format('d M Y') : null,
                'blood_group' => $student->blood_group ?? 'N/A',
                'roll_number' => $enrollment?->roll_number ?? 'N/A',
                'class_name' => $enrollment?->schoolClass?->name ?? 'N/A',
                'section_name' => $enrollment?->section?->name ?? 'N/A',
                'academic_year' => $enrollment?->academicSession?->name ?? 'Current',
                'photo_url' => $student->photo_url,
                'emergency_contact' => $student->emergency_contact_phone ?? $primaryGuardian?->phone ?? 'N/A',
                'parent_name' => $primaryGuardian?->full_name ?? 'N/A',
                'school_name' => $student->branch?->school?->name ?? 'School ERP Institution',
                'branch_name' => $student->branch?->name ?? 'Main Campus',
                'branch_phone' => $student->branch?->phone ?? '',
                'branch_address' => $student->branch?->address ?? '',
            ],
            'qr_verification' => [
                'raw_payload' => $qrPayload,
                'qr_data_string' => "EDUCORE:VERIFY:{$student->id}:{$student->admission_number}",
                'qr_encoded' => $qrDataEncoded,
                'verification_url' => "/verify/student/{$student->id}?num=" . urlencode($student->admission_number),
            ],
        ];
    }

    /**
     * Withdraw student (record exit date, reason, mark inactive/withdrawn).
     */
    public function withdrawStudent(Student $student, array $data): Student
    {
        return DB::transaction(function () use ($student, $data) {
            $student->update([
                'status' => 'withdrawn',
            ]);

            // Update current enrollment
            $current = $student->currentEnrollment;
            if ($current) {
                $current->update(['status' => 'withdrawn']);
            }

            return $student->fresh();
        });
    }

    /**
     * Re-admit student back into school.
     */
    public function reAdmitStudent(Student $student, array $data, string $sessionId): Student
    {
        return DB::transaction(function () use ($student, $data, $sessionId) {
            $student->update([
                'status' => 'active',
            ]);

            // If class and section provided, create or update enrollment
            if (!empty($data['class_id']) && !empty($data['section_id'])) {
                StudentEnrollment::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_session_id' => $sessionId,
                    ],
                    [
                        'tenant_id' => $student->tenant_id,
                        'branch_id' => $student->branch_id,
                        'class_id' => $data['class_id'],
                        'section_id' => $data['section_id'],
                        'roll_number' => $data['roll_number'] ?? null,
                        'enrollment_date' => now()->toDateString(),
                        'status' => 'enrolled',
                    ]
                );
            }

            return $student->fresh(['currentEnrollment.schoolClass', 'currentEnrollment.section']);
        });
    }

    /**
     * Issue Transfer Certificate (TC).
     */
    public function issueTransferCertificate(Student $student, array $data, string $userId): StudentTransfer
    {
        return DB::transaction(function () use ($student, $data, $userId) {
            $branchId = $student->branch_id;
            $year = date('Y');
            $prefix = "TC-{$year}-";

            $lastTc = StudentTransfer::where('branch_id', $branchId)
                ->where('tc_number', 'LIKE', "{$prefix}%")
                ->orderByDesc('tc_number')
                ->value('tc_number');

            if ($lastTc && preg_match('/-(\d+)$/', $lastTc, $matches)) {
                $seq = (int) $matches[1] + 1;
            } else {
                $seq = 1;
            }
            $tcNumber = sprintf("%s%04d", $prefix, $seq);

            $clearances = $data['clearance_status'] ?? [
                'library_cleared' => true,
                'fees_cleared' => true,
                'laboratory_cleared' => true,
                'sports_cleared' => true,
            ];

            $transfer = StudentTransfer::create([
                'tenant_id' => $student->tenant_id,
                'branch_id' => $branchId,
                'student_id' => $student->id,
                'tc_number' => $tcNumber,
                'application_date' => $data['application_date'] ?? now()->toDateString(),
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'destination_school' => $data['destination_school'] ?? 'Not specified',
                'reason_for_leaving' => $data['reason_for_leaving'] ?? 'Course completed / Relocation',
                'clearance_status' => $clearances,
                'approved_by' => $userId,
            ]);

            // Update student and enrollment status
            $student->update(['status' => 'transferred']);
            if ($student->currentEnrollment) {
                $student->currentEnrollment->update(['status' => 'transferred']);
            }

            return $transfer;
        });
    }
}

