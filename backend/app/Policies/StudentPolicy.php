<?php

namespace App\Policies;

use App\Models\ClassTeacher;
use App\Models\Employee;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\TeacherSubject;
use App\Models\User;

class StudentPolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view any student records.
     */
    public function viewAny(User $user): bool
    {
        return $this->userHasRole($user, [
            'school_admin', 'school_owner', 'principal', 'vice_principal',
            'admission_officer', 'teacher', 'class_teacher', 'accountant',
        ]);
    }

    /**
     * Determine whether the user can view a specific student record.
     */
    public function view(User $user, Student $student): bool
    {
        // 1. Cross-tenant isolation check
        if (!$this->belongsToActiveTenant($student)) {
            return false;
        }

        // 2. Administrators, Principals, and Admissions have full school/branch visibility
        if ($this->userHasRole($user, ['school_admin', 'school_owner', 'principal', 'vice_principal', 'admission_officer', 'accountant'])) {
            return $this->belongsToActiveBranch($student);
        }

        // 3. Parent / Guardian Access Check: ONLY linked children
        if ($this->userHasRole($user, 'parent')) {
            $guardianIds = Guardian::where('user_id', $user->id)->pluck('id');

            if ($guardianIds->isEmpty()) {
                return false;
            }

            return StudentGuardian::where('student_id', $student->id)
                ->whereIn('guardian_id', $guardianIds)
                ->exists();
        }

        // 4. Student Self-Access Check
        if ($this->userHasRole($user, 'student')) {
            return $student->user_id === $user->id;
        }

        // 5. Teacher Access Check: ONLY students in assigned classes / subjects
        if ($this->userHasRole($user, ['teacher', 'class_teacher'])) {
            $employee = Employee::where('user_id', $user->id)->first();
            if (!$employee) {
                return false;
            }

            // Get active enrollment for the student
            $enrollments = $student->enrollments()->where('status', 'enrolled')->get();
            if ($enrollments->isEmpty()) {
                return false;
            }

            foreach ($enrollments as $enrollment) {
                // Check if class teacher for this section
                $isClassTeacher = ClassTeacher::where('employee_id', $employee->id)
                    ->where('class_id', $enrollment->class_id)
                    ->where('section_id', $enrollment->section_id)
                    ->exists();

                if ($isClassTeacher) {
                    return true;
                }

                // Check if subject teacher for this class
                $isSubjectTeacher = TeacherSubject::where('employee_id', $employee->id)
                    ->whereHas('classSubject', function ($q) use ($enrollment) {
                        $q->where('class_id', $enrollment->class_id);
                    })
                    ->exists();

                if ($isSubjectTeacher) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Determine whether the user can create students.
     */
    public function create(User $user): bool
    {
        return $this->userHasRole($user, ['school_admin', 'school_owner', 'principal', 'admission_officer']);
    }

    /**
     * Determine whether the user can update the student.
     */
    public function update(User $user, Student $student): bool
    {
        if (!$this->belongsToActiveTenant($student)) {
            return false;
        }

        return $this->userHasRole($user, ['school_admin', 'school_owner', 'principal', 'admission_officer']);
    }

    /**
     * Determine whether the user can delete the student.
     */
    public function delete(User $user, Student $student): bool
    {
        if (!$this->belongsToActiveTenant($student)) {
            return false;
        }

        return $this->userHasRole($user, ['school_admin', 'school_owner']);
    }
}
