<?php

namespace App\Http\Middleware;

use App\Exceptions\Tenancy\BranchAccessDeniedException;
use App\Models\SchoolBranch;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBranchAccess
{
    public function __construct(
        protected TenantContext $context
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $branchId = $request->header('X-Branch-ID') ?: $request->header('x-branch-id');

        if (!$branchId) {
            return $next($request);
        }

        $tenantId = $this->context->getTenantId();

        // 1. Verify branch belongs to current tenant
        $branch = SchoolBranch::where('id', $branchId)->first();

        if (!$branch || ($tenantId && $branch->tenant_id !== $tenantId)) {
            throw new BranchAccessDeniedException("Branch does not belong to the active school organization.");
        }

        // 2. If user is authenticated and not super admin, check membership branch binding
        if ($user && !$user->is_platform_admin) {
            $membership = $this->context->getActiveMembership();

            if ($membership && $membership->branch_id && $membership->branch_id !== $branchId && !$membership->is_owner) {
                throw new BranchAccessDeniedException("User is restricted to branch '{$membership->branch_id}'.");
            }
        }

        $this->context->setBranch($branch);

        return $next($request);
    }
}

