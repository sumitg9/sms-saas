<?php

namespace App\Services\Student;

use App\Models\Guardian;
use App\Models\ImportJob;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentImportExportService
{
    /**
     * Standard import CSV / Excel template columns and sample row.
     */
    public function getImportTemplate(): array
    {
        return [
            'columns' => [
                'first_name' => ['required' => true, 'type' => 'string', 'example' => 'Aarav'],
                'middle_name' => ['required' => false, 'type' => 'string', 'example' => 'Kumar'],
                'last_name' => ['required' => true, 'type' => 'string', 'example' => 'Sharma'],
                'admission_number' => ['required' => false, 'type' => 'string', 'note' => 'Leave blank to auto-generate', 'example' => 'STU-2026-0045'],
                'admission_date' => ['required' => false, 'type' => 'date (YYYY-MM-DD)', 'example' => date('Y-m-d')],
                'date_of_birth' => ['required' => true, 'type' => 'date (YYYY-MM-DD)', 'example' => '2016-08-15'],
                'gender' => ['required' => true, 'type' => 'male|female|other', 'example' => 'male'],
                'blood_group' => ['required' => false, 'type' => 'string (A+, B+, O+, AB+, etc.)', 'example' => 'O+'],
                'class_name' => ['required' => true, 'type' => 'string', 'example' => 'Grade 5'],
                'section_name' => ['required' => true, 'type' => 'string', 'example' => 'A'],
                'roll_number' => ['required' => false, 'type' => 'string', 'example' => '12'],
                'guardian_name' => ['required' => true, 'type' => 'string', 'example' => 'Rajesh Sharma'],
                'guardian_phone' => ['required' => true, 'type' => 'string', 'example' => '+919876543210'],
                'guardian_email' => ['required' => false, 'type' => 'email', 'example' => 'rajesh.sharma@example.com'],
                'guardian_relation' => ['required' => false, 'type' => 'father|mother|guardian', 'example' => 'father'],
                'current_address' => ['required' => false, 'type' => 'string', 'example' => '42 Park Street, Bangalore'],
                'emergency_contact_phone' => ['required' => false, 'type' => 'string', 'example' => '+919876543210'],
            ],
            'sample_rows' => [
                [
                    'first_name' => 'Aarav',
                    'middle_name' => 'Kumar',
                    'last_name' => 'Sharma',
                    'admission_number' => '',
                    'admission_date' => date('Y-m-d'),
                    'date_of_birth' => '2016-08-15',
                    'gender' => 'male',
                    'blood_group' => 'O+',
                    'class_name' => 'Grade 5',
                    'section_name' => 'A',
                    'roll_number' => '12',
                    'guardian_name' => 'Rajesh Sharma',
                    'guardian_phone' => '+919876543210',
                    'guardian_email' => 'rajesh.sharma@example.com',
                    'guardian_relation' => 'father',
                    'current_address' => '42 Park Street, Bangalore',
                    'emergency_contact_phone' => '+919876543210',
                ],
                [
                    'first_name' => 'Ananya',
                    'middle_name' => '',
                    'last_name' => 'Patel',
                    'admission_number' => '',
                    'admission_date' => date('Y-m-d'),
                    'date_of_birth' => '2017-03-22',
                    'gender' => 'female',
                    'blood_group' => 'B+',
                    'class_name' => 'Grade 4',
                    'section_name' => 'B',
                    'roll_number' => '05',
                    'guardian_name' => 'Priya Patel',
                    'guardian_phone' => '+919876543211',
                    'guardian_email' => 'priya.patel@example.com',
                    'guardian_relation' => 'mother',
                    'current_address' => '15 Lake View, Mumbai',
                    'emergency_contact_phone' => '+919876543211',
                ],
            ],
        ];
    }

    /**
     * Preview and validate bulk import rows.
     */
    public function previewImport(array $rows, string $branchId, string $sessionId): array
    {
        $preview = [];
        $errors = [];
        $validCount = 0;
        $invalidCount = 0;

        // Pre-load classes and sections in branch for fast lookup
        $classes = SchoolClass::where('branch_id', $branchId)
            ->with('sections')
            ->get();

        $existingAdmissionNumbers = Student::where('branch_id', $branchId)
            ->pluck('admission_number')
            ->flip()
            ->all();

        foreach ($rows as $index => $row) {
            $rowErrors = [];
            $rowNum = $index + 1;

            // 1. Mandatory demographic validation
            if (empty($row['first_name'])) {
                $rowErrors[] = "Row {$rowNum}: 'first_name' is required.";
            }
            if (empty($row['last_name'])) {
                $rowErrors[] = "Row {$rowNum}: 'last_name' is required.";
            }
            if (empty($row['date_of_birth'])) {
                $rowErrors[] = "Row {$rowNum}: 'date_of_birth' is required.";
            } elseif (!strtotime($row['date_of_birth'])) {
                $rowErrors[] = "Row {$rowNum}: 'date_of_birth' must be a valid date format (YYYY-MM-DD).";
            }
            if (empty($row['gender']) || !in_array(strtolower($row['gender']), ['male', 'female', 'other'], true)) {
                $rowErrors[] = "Row {$rowNum}: 'gender' must be 'male', 'female', or 'other'.";
            }

            // 2. Class and Section resolution
            $className = trim($row['class_name'] ?? '');
            $sectionName = trim($row['section_name'] ?? '');

            if (empty($className)) {
                $rowErrors[] = "Row {$rowNum}: 'class_name' is required.";
            }
            if (empty($sectionName)) {
                $rowErrors[] = "Row {$rowNum}: 'section_name' is required.";
            }

            $matchedClass = null;
            $matchedSection = null;

            if ($className) {
                $matchedClass = $classes->first(function ($c) use ($className) {
                    return strcasecmp($c->name, $className) === 0 || strcasecmp($c->code, $className) === 0;
                });

                if (!$matchedClass) {
                    $rowErrors[] = "Row {$rowNum}: Class '{$className}' not found in branch.";
                } elseif ($sectionName) {
                    $matchedSection = $matchedClass->sections->first(function ($s) use ($sectionName) {
                        return strcasecmp($s->name, $sectionName) === 0;
                    });

                    if (!$matchedSection) {
                        $rowErrors[] = "Row {$rowNum}: Section '{$sectionName}' not found in class '{$className}'.";
                    }
                }
            }

            // 3. Guardian validation
            if (empty($row['guardian_phone'])) {
                $rowErrors[] = "Row {$rowNum}: 'guardian_phone' is required.";
            }
            if (empty($row['guardian_name'])) {
                $rowErrors[] = "Row {$rowNum}: 'guardian_name' is required.";
            }

            // 4. Duplicate admission number check
            $admNum = trim($row['admission_number'] ?? '');
            if ($admNum !== '' && isset($existingAdmissionNumbers[$admNum])) {
                $rowErrors[] = "Row {$rowNum}: Admission number '{$admNum}' already exists in this branch.";
            }

            $isValid = empty($rowErrors);
            if ($isValid) {
                $validCount++;
            } else {
                $invalidCount++;
                $errors = array_merge($errors, $rowErrors);
            }

            $preview[] = [
                'row_number' => $rowNum,
                'data' => $row,
                'is_valid' => $isValid,
                'class_id' => $matchedClass?->id,
                'section_id' => $matchedSection?->id,
                'errors' => $rowErrors,
            ];
        }

        return [
            'total_rows' => count($rows),
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'preview' => $preview,
            'errors' => $errors,
        ];
    }

    /**
     * Process validated bulk import and record an ImportJob audit.
     */
    public function processImport(
        array $rows,
        string $tenantId,
        string $schoolId,
        string $branchId,
        string $sessionId,
        string $userId
    ): array {
        $previewResult = $this->previewImport($rows, $branchId, $sessionId);

        $job = ImportJob::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'import_type' => 'students',
            'file_path' => 'bulk_payload_' . time() . '.json',
            'status' => 'processing',
            'total_rows' => $previewResult['total_rows'],
            'processed_rows' => 0,
            'error_rows' => 0,
            'errors' => [],
        ]);

        $processed = 0;
        $failed = 0;
        $processingErrors = [];
        $admissionService = new AdmissionService();

        DB::beginTransaction();
        try {
            foreach ($previewResult['preview'] as $item) {
                if (!$item['is_valid']) {
                    $failed++;
                    $processingErrors[] = [
                        'row' => $item['row_number'],
                        'errors' => $item['errors'],
                    ];
                    continue;
                }

                $row = $item['data'];
                $classId = $item['class_id'];
                $sectionId = $item['section_id'];

                $admNum = !empty($row['admission_number'])
                    ? trim($row['admission_number'])
                    : $admissionService->generateAdmissionNumber($branchId);

                // Create Student
                $student = Student::create([
                    'tenant_id' => $tenantId,
                    'school_id' => $schoolId,
                    'branch_id' => $branchId,
                    'admission_number' => $admNum,
                    'admission_date' => $row['admission_date'] ?? now()->toDateString(),
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?? null,
                    'last_name' => $row['last_name'],
                    'gender' => strtolower($row['gender']),
                    'date_of_birth' => $row['date_of_birth'],
                    'blood_group' => $row['blood_group'] ?? null,
                    'emergency_contact_phone' => $row['emergency_contact_phone'] ?? $row['guardian_phone'],
                    'current_address' => $row['current_address'] ?? null,
                    'permanent_address' => $row['current_address'] ?? null,
                    'status' => 'active',
                ]);

                // Create Enrollment
                StudentEnrollment::create([
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'student_id' => $student->id,
                    'academic_session_id' => $sessionId,
                    'class_id' => $classId,
                    'section_id' => $sectionId,
                    'roll_number' => $row['roll_number'] ?? null,
                    'enrollment_date' => $row['admission_date'] ?? now()->toDateString(),
                    'status' => 'enrolled',
                ]);

                // Create / Link Guardian
                $nameParts = explode(' ', trim($row['guardian_name']), 2);
                $gFirst = $nameParts[0];
                $gLast = $nameParts[1] ?? 'Guardian';

                $guardian = Guardian::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'phone' => trim($row['guardian_phone']),
                    ],
                    [
                        'first_name' => $gFirst,
                        'last_name' => $gLast,
                        'relation_type' => $row['guardian_relation'] ?? 'father',
                        'email' => $row['guardian_email'] ?? null,
                        'address' => $row['current_address'] ?? null,
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

                $processed++;
            }

            DB::commit();

            $job->update([
                'status' => 'completed',
                'processed_rows' => $processed,
                'error_rows' => $failed,
                'errors' => $processingErrors,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            $job->update([
                'status' => 'failed',
                'error_rows' => count($rows),
                'errors' => [['error' => $e->getMessage()]],
            ]);

            throw $e;
        }

        return [
            'job_id' => $job->id,
            'status' => $job->status,
            'total_rows' => count($rows),
            'processed_rows' => $processed,
            'error_rows' => $failed,
            'errors' => $processingErrors,
        ];
    }

    /**
     * Export student records matching branch and filters.
     */
    public function exportStudents(
        string $branchId,
        ?string $sessionId = null,
        ?string $classId = null,
        ?string $sectionId = null,
        ?string $status = null
    ): array {
        $query = Student::where('branch_id', $branchId)
            ->with([
                'currentEnrollment.schoolClass',
                'currentEnrollment.section',
                'currentEnrollment.academicSession',
                'guardians',
            ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($classId || $sectionId || $sessionId) {
            $query->whereHas('currentEnrollment', function ($q) use ($classId, $sectionId, $sessionId) {
                if ($classId) {
                    $q->where('class_id', $classId);
                }
                if ($sectionId) {
                    $q->where('section_id', $sectionId);
                }
                if ($sessionId) {
                    $q->where('academic_session_id', $sessionId);
                }
            });
        }

        $students = $query->orderBy('admission_number')->get();

        return $students->map(function ($s) {
            $enrollment = $s->currentEnrollment;
            $guardian = $s->guardians->first(fn($g) => $g->pivot->is_primary) ?? $s->guardians->first();

            return [
                'id' => $s->id,
                'admission_number' => $s->admission_number,
                'admission_date' => $s->admission_date ? $s->admission_date->toDateString() : '',
                'full_name' => $s->full_name,
                'first_name' => $s->first_name,
                'middle_name' => $s->middle_name ?? '',
                'last_name' => $s->last_name,
                'gender' => $s->gender,
                'date_of_birth' => $s->date_of_birth ? $s->date_of_birth->toDateString() : '',
                'blood_group' => $s->blood_group ?? '',
                'class_name' => $enrollment?->schoolClass?->name ?? '',
                'section_name' => $enrollment?->section?->name ?? '',
                'roll_number' => $enrollment?->roll_number ?? '',
                'academic_session' => $enrollment?->academicSession?->name ?? '',
                'guardian_name' => $guardian?->full_name ?? '',
                'guardian_phone' => $guardian?->phone ?? '',
                'guardian_email' => $guardian?->email ?? '',
                'guardian_relation' => $guardian?->relation_type ?? '',
                'emergency_contact_phone' => $s->emergency_contact_phone ?? '',
                'current_address' => $s->current_address ?? '',
                'status' => $s->status,
            ];
        })->toArray();
    }
}
