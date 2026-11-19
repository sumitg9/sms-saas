<?php

namespace App\Services\Academic;

use App\Models\ClassSubject;
use App\Models\Subject;
use App\Models\SubjectGroup;
use App\Models\TeacherSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubjectManagementService
{
    /**
     * Create a subject ensuring unique subject code in the branch.
     */
    public function createSubject(array $data, string $tenantId, string $schoolId, string $branchId): Subject
    {
        $code = strtoupper(trim($data['code']));
        $name = trim($data['name']);

        $exists = Subject::where('branch_id', $branchId)
            ->whereRaw('UPPER(code) = ?', [$code])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => ["Subject with code '{$code}' already exists in this branch."],
            ]);
        }

        return Subject::create([
            'tenant_id' => $tenantId,
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'name' => $name,
            'code' => $code,
            'type' => $data['type'] ?? 'theory',
            'credit_hours' => $data['credit_hours'] ?? 1.0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing subject.
     */
    public function updateSubject(Subject $subject, array $data): Subject
    {
        if (isset($data['code'])) {
            $code = strtoupper(trim($data['code']));
            $exists = Subject::where('branch_id', $subject->branch_id)
                ->where('id', '!=', $subject->id)
                ->whereRaw('UPPER(code) = ?', [$code])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'code' => ["Subject with code '{$code}' already exists in this branch."],
                ]);
            }
            $data['code'] = $code;
        }

        $subject->update($data);
        return $subject->fresh();
    }

    /**
     * Assign a subject to a class for a specific academic session.
     */
    public function assignSubjectToClass(array $data, string $tenantId, string $branchId): ClassSubject
    {
        $classId = $data['class_id'];
        $subjectId = $data['subject_id'];
        $sessionId = $data['academic_session_id'];

        $exists = ClassSubject::where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('academic_session_id', $sessionId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'subject_id' => ['This subject is already assigned to the class for the specified academic session.'],
            ]);
        }

        return ClassSubject::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'academic_session_id' => $sessionId,
            'is_elective' => $data['is_elective'] ?? false,
            'max_weekly_periods' => $data['max_weekly_periods'] ?? 5,
        ]);
    }

    /**
     * Assign a teacher to a class subject.
     */
    public function assignTeacherToSubject(
        string $classSubjectId,
        string $employeeId,
        string $tenantId,
        string $branchId,
        bool $isPrimary = true
    ): TeacherSubject {
        return DB::transaction(function () use ($classSubjectId, $employeeId, $tenantId, $branchId, $isPrimary) {
            if ($isPrimary) {
                TeacherSubject::where('class_subject_id', $classSubjectId)
                    ->update(['is_primary' => false]);
            }

            return TeacherSubject::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'class_subject_id' => $classSubjectId,
                ],
                [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'is_primary' => $isPrimary,
                ]
            );
        });
    }

    /**
     * Create a subject group (e.g. Science, Commerce, Arts).
     */
    public function createSubjectGroup(array $data, string $tenantId, string $branchId): SubjectGroup
    {
        $name = trim($data['name']);

        $exists = SubjectGroup::where('branch_id', $branchId)
            ->whereRaw('UPPER(name) = ?', [strtoupper($name)])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ["Subject group '{$name}' already exists in this branch."],
            ]);
        }

        return SubjectGroup::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'name' => $name,
            'description' => $data['description'] ?? null,
        ]);
    }
}

