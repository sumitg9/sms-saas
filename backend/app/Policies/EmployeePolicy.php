<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view any employee records.
     */
    public function viewAny(User $user): bool
    {
        return $this->userHasRole($user, [
            'school_admin', 'school_owner', 'principal', 'vice_principal',
            'hr_manager', 'accountant',
        ]);
    }

    /**
     * Determine whether the user can view the specific employee record.
     */
    public function view(User $user, Employee $employee): bool
    {
        if (!$this->belongsToActiveTenant($employee)) {
            return false;
        }

        // Admins, Principals, HR, and Accountants can view in active branch
        if ($this->userHasRole($user, ['school_admin', 'school_owner', 'principal', 'vice_principal', 'hr_manager', 'accountant'])) {
            return $this->belongsToActiveBranch($employee);
        }

        // Individual staff can only view their own record
        return $employee->user_id === $user->id;
    }

    /**
     * Determine whether the user can create employees.
     */
    public function create(User $user): bool
    {
        return $this->userHasRole($user, ['school_admin', 'school_owner', 'hr_manager']);
    }

    /**
     * Determine whether the user can update employee information.
     */
    public function update(User $user, Employee $employee): bool
    {
        if (!$this->belongsToActiveTenant($employee) || !$this->belongsToActiveBranch($employee)) {
            return false;
        }

        return $this->userHasRole($user, ['school_admin', 'school_owner', 'hr_manager']);
    }

    /**
     * Determine whether the user can delete an employee.
     */
    public function delete(User $user, Employee $employee): bool
    {
        if (!$this->belongsToActiveTenant($employee) || !$this->belongsToActiveBranch($employee)) {
            return false;
        }

        return $this->userHasRole($user, ['school_admin', 'school_owner', 'hr_manager']);
    }

    /**
     * Determine whether the user can view confidential payroll/contracts.
     */
    public function viewConfidential(User $user, Employee $employee): bool
    {
        if (!$this->belongsToActiveTenant($employee) || !$this->belongsToActiveBranch($employee)) {
            return false;
        }

        if ($this->userHasRole($user, ['school_admin', 'school_owner', 'hr_manager', 'accountant'])) {
            return true;
        }

        return $employee->user_id === $user->id;
    }
}

