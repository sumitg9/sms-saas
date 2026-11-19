<?php

namespace App\Services\Tenancy;

use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Support\Facades\DB;

class TenantContext
{
    protected ?Tenant $tenant = null;
    protected ?School $school = null;
    protected ?SchoolBranch $branch = null;
    protected ?TenantMembership $activeMembership = null;
    protected bool $isSuperAdmin = false;
    protected bool $ignoreTenantScope = false;

    /**
     * Set the currently active tenant.
     */
    public function setTenant(?Tenant $tenant): self
    {
        $this->tenant = $tenant;

        if ($tenant) {
            config(['app.current_tenant_id' => $tenant->id]);
            $this->syncPostgresSession($tenant->id, $this->isSuperAdmin);
        } else {
            config(['app.current_tenant_id' => null]);
            $this->syncPostgresSession(null, $this->isSuperAdmin);
        }

        return $this;
    }

    /**
     * Get the currently active tenant model.
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Get the UUID of the currently active tenant.
     */
    public function getTenantId(): ?string
    {
        return $this->tenant?->id ?? config('app.current_tenant_id');
    }

    /**
     * Determine if a tenant is currently resolved.
     */
    public function hasTenant(): bool
    {
        return !empty($this->getTenantId());
    }

    /**
     * Set the currently active school.
     */
    public function setSchool(?School $school): self
    {
        $this->school = $school;
        config(['app.current_school_id' => $school?->id]);

        return $this;
    }

    /**
     * Get the currently active school model.
     */
    public function getSchool(): ?School
    {
        return $this->school;
    }

    /**
     * Get the UUID of the currently active school.
     */
    public function getSchoolId(): ?string
    {
        return $this->school?->id ?? config('app.current_school_id');
    }

    /**
     * Set the currently active branch.
     */
    public function setBranch(?SchoolBranch $branch): self
    {
        $this->branch = $branch;
        config(['app.current_branch_id' => $branch?->id]);

        return $this;
    }

    /**
     * Get the currently active branch model.
     */
    public function getBranch(): ?SchoolBranch
    {
        return $this->branch;
    }

    /**
     * Get the UUID of the currently active branch.
     */
    public function getBranchId(): ?string
    {
        return $this->branch?->id ?? config('app.current_branch_id');
    }

    /**
     * Set the active user membership in the tenant.
     */
    public function setActiveMembership(?TenantMembership $membership): self
    {
        $this->activeMembership = $membership;

        return $this;
    }

    /**
     * Get the active user membership in the tenant.
     */
    public function getActiveMembership(): ?TenantMembership
    {
        return $this->activeMembership;
    }

    /**
     * Set super admin status for the current execution.
     */
    public function setSuperAdmin(bool $isSuperAdmin): self
    {
        $this->isSuperAdmin = $isSuperAdmin;
        $this->syncPostgresSession($this->getTenantId(), $isSuperAdmin);

        return $this;
    }

    /**
     * Check if the context is executing as a platform super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    /**
     * Determine if the tenant global scope should be ignored.
     */
    public function shouldIgnoreTenantScope(): bool
    {
        return $this->ignoreTenantScope;
    }

    /**
     * Execute a callback within the context of a specific tenant.
     * Restores previous context state upon completion.
     */
    public function executeAsTenant(Tenant $tenant, callable $callback, ?SchoolBranch $branch = null): mixed
    {
        $previousTenant = $this->tenant;
        $previousSchool = $this->school;
        $previousBranch = $this->branch;
        $previousMembership = $this->activeMembership;
        $previousIgnoreScope = $this->ignoreTenantScope;

        try {
            $this->setTenant($tenant);
            if ($branch) {
                $this->setBranch($branch);
                if ($branch->school) {
                    $this->setSchool($branch->school);
                }
            }

            return $callback();
        } finally {
            $this->setTenant($previousTenant);
            $this->setSchool($previousSchool);
            $this->setBranch($previousBranch);
            $this->setActiveMembership($previousMembership);
            $this->ignoreTenantScope = $previousIgnoreScope;
        }
    }

    /**
     * Execute a callback temporarily bypassing tenant scoping.
     * Useful for platform administrative lookups or cross-tenant reporting.
     */
    public function withoutTenancy(callable $callback): mixed
    {
        $previousIgnore = $this->ignoreTenantScope;
        $this->ignoreTenantScope = true;

        try {
            return $callback();
        } finally {
            $this->ignoreTenantScope = $previousIgnore;
        }
    }

    /**
     * Reset the entire context to empty/initial state.
     */
    public function reset(): void
    {
        $this->tenant = null;
        $this->school = null;
        $this->branch = null;
        $this->activeMembership = null;
        $this->isSuperAdmin = false;
        $this->ignoreTenantScope = false;

        config([
            'app.current_tenant_id' => null,
            'app.current_school_id' => null,
            'app.current_branch_id' => null,
        ]);

        $this->syncPostgresSession(null, false);
    }

    /**
     * Synchronize tenant context with PostgreSQL session variables for Row-Level Security (RLS).
     */
    protected function syncPostgresSession(?string $tenantId, bool $isSuperAdmin): void
    {
        try {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $tenantSetting = $tenantId ? "'{$tenantId}'" : "''";
                $superAdminSetting = $isSuperAdmin ? "'true'" : "'false'";

                DB::statement("SET app.current_tenant_id = {$tenantSetting}");
                DB::statement("SET app.is_super_admin = {$superAdminSetting}");
            }
        } catch (\Throwable $e) {
            // Ignore if connection is not yet initialized (e.g. during early boot)
        }
    }
}

