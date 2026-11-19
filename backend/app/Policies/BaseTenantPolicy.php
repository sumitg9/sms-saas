<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

abstract class BaseTenantPolicy
{
    /**
     * Platform Super Admins bypass standard tenant restrictions for system maintenance.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_platform_admin) {
            return true;
        }

        return null;
    }

    /**
     * Verify that the target record belongs strictly to the active tenant context.
     */
    protected function belongsToActiveTenant(Model $model): bool
    {
        $currentTenantId = app(TenantContext::class)->getTenantId();

        if (empty($currentTenantId) || empty($model->tenant_id)) {
            return false;
        }

        return $model->tenant_id === $currentTenantId;
    }

    /**
     * Verify that the target record belongs to the active branch context if set.
     */
    protected function belongsToActiveBranch(Model $model): bool
    {
        $currentBranchId = app(TenantContext::class)->getBranchId();

        if (!$currentBranchId || !isset($model->branch_id)) {
            return true;
        }

        return $model->branch_id === $currentBranchId;
    }

    /**
     * Check if user has a specific global or tenant role.
     */
    protected function userHasRole(User $user, string|array $roles): bool
    {
        $roles = (array) $roles;
        $tenantId = app(TenantContext::class)->getTenantId();

        return $user->userRoles()
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->whereHas('role', function ($q) use ($roles) {
                $q->whereIn('name', $roles);
            })
            ->exists();
    }
}

