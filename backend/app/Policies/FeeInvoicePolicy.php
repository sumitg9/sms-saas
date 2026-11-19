<?php

namespace App\Policies;

use App\Models\FeeInvoice;
use App\Models\Guardian;
use App\Models\StudentGuardian;
use App\Models\User;

class FeeInvoicePolicy extends BaseTenantPolicy
{
    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, FeeInvoice $invoice): bool
    {
        // 1. Cross-tenant isolation check
        if (!$this->belongsToActiveTenant($invoice)) {
            return false;
        }

        // 2. Administrators & Accountants
        if ($this->userHasRole($user, ['school_admin', 'school_owner', 'accountant', 'principal'])) {
            return $this->belongsToActiveBranch($invoice);
        }

        // 3. Parent Access: ONLY invoices for linked children
        if ($this->userHasRole($user, 'parent')) {
            $guardianIds = Guardian::where('user_id', $user->id)->pluck('id');

            return StudentGuardian::where('student_id', $invoice->student_id)
                ->whereIn('guardian_id', $guardianIds)
                ->exists();
        }

        // 4. Student Self-Access
        if ($this->userHasRole($user, 'student')) {
            return $invoice->student && $invoice->student->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create fee invoices.
     */
    public function create(User $user): bool
    {
        return $this->userHasRole($user, ['school_admin', 'accountant']);
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, FeeInvoice $invoice): bool
    {
        if (!$this->belongsToActiveTenant($invoice)) {
            return false;
        }

        return $this->userHasRole($user, ['school_admin', 'accountant']);
    }

    /**
     * Determine whether the user can delete/cancel the invoice.
     */
    public function delete(User $user, FeeInvoice $invoice): bool
    {
        if (!$this->belongsToActiveTenant($invoice)) {
            return false;
        }

        return $this->userHasRole($user, ['school_admin', 'school_owner']);
    }
}

