<?php

namespace App\Jobs\Middleware;

use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Closure;

class ApplyTenantContext
{
    public function __construct(
        protected ?string $tenantId = null,
        protected ?string $branchId = null,
        protected ?string $schoolId = null
    ) {}

    /**
     * Process the queued job within the serialized tenant context.
     */
    public function handle(object $job, Closure $next): mixed
    {
        $context = app(TenantContext::class);
        $previousTenant = $context->getTenant();
        $previousBranch = $context->getBranch();
        $previousSchool = $context->getSchool();

        try {
            if ($this->tenantId) {
                $tenant = Tenant::find($this->tenantId);
                if ($tenant) {
                    $context->setTenant($tenant);
                }
            }

            if ($this->branchId) {
                $branch = SchoolBranch::find($this->branchId);
                if ($branch) {
                    $context->setBranch($branch);
                }
            }

            if ($this->schoolId) {
                $school = School::find($this->schoolId);
                if ($school) {
                    $context->setSchool($school);
                }
            }

            return $next($job);
        } finally {
            $context->setTenant($previousTenant);
            $context->setBranch($previousBranch);
            $context->setSchool($previousSchool);
        }
    }
}

