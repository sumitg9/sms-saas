<?php

namespace App\Jobs\Traits;

use App\Jobs\Middleware\ApplyTenantContext;
use App\Services\Tenancy\TenantContext;

trait TenantAwareJob
{
    public ?string $tenantId = null;
    public ?string $branchId = null;
    public ?string $schoolId = null;

    /**
     * Capture the current tenant context when instantiating the job.
     */
    public function captureTenantContext(?string $tenantId = null, ?string $branchId = null, ?string $schoolId = null): self
    {
        $context = app(TenantContext::class);

        $this->tenantId = $tenantId ?: $context->getTenantId();
        $this->branchId = $branchId ?: $context->getBranchId();
        $this->schoolId = $schoolId ?: $context->getSchoolId();

        return $this;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            new ApplyTenantContext($this->tenantId, $this->branchId, $this->schoolId),
        ];
    }
}

