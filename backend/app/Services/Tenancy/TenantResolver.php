<?php

namespace App\Services\Tenancy;

use App\Exceptions\Tenancy\BranchAccessDeniedException;
use App\Exceptions\Tenancy\TenantAccessDeniedException;
use App\Exceptions\Tenancy\TenantInactiveException;
use App\Exceptions\Tenancy\TenantNotFoundException;
use App\Exceptions\Tenancy\TenantSuspendedException;
use App\Models\SchoolBranch;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Http\Request;

class TenantResolver
{
    public function __construct(
        protected TenantContext $context
    ) {}

    /**
     * Resolve the tenant and optionally branch for an incoming HTTP request.
     */
    public function resolve(Request $request): ?Tenant
    {
        $tenant = null;
        $user = $request->user();

        // 1. Try resolving via Domain or Subdomain
        $domainTenant = $this->resolveFromDomain($request);
        if ($domainTenant) {
            $tenant = $domainTenant;
        }

        // 2. Check for explicit X-Tenant-ID header (requires verification)
        $headerTenantId = $request->header('X-Tenant-ID') ?: $request->header('x-tenant-id');

        if ($headerTenantId) {
            $requestedTenant = Tenant::find($headerTenantId);

            if (!$requestedTenant) {
                throw new TenantNotFoundException("School tenant with ID '{$headerTenantId}' not found.");
            }

            // Security Rule: Validate authenticated user's membership if user is authenticated
            if ($user) {
                if ($user->is_platform_admin) {
                    $tenant = $requestedTenant;
                    $this->context->setSuperAdmin(true);
                } else {
                    $membership = TenantMembership::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('tenant_id', $requestedTenant->id)
                        ->where('is_active', true)
                        ->first();

                    if (!$membership) {
                        throw new TenantAccessDeniedException(
                            "Unauthorized tenant switch: User does not have active membership in tenant '{$requestedTenant->name}'."
                        );
                    }

                    $tenant = $requestedTenant;
                    $this->context->setActiveMembership($membership);
                }
            } else {
                // If unauthenticated (e.g., login or public inquiry page), ensure it matches domain if domain was resolved
                if ($domainTenant && $domainTenant->id !== $requestedTenant->id) {
                    throw new TenantAccessDeniedException('Requested tenant does not match the active school domain.');
                }
                $tenant = $requestedTenant;
            }
        } elseif (!$tenant && $user) {
            // 3. Fallback to authenticated user's active membership if no domain or header
            if ($user->is_platform_admin) {
                $this->context->setSuperAdmin(true);
            } else {
                $membership = TenantMembership::withoutGlobalScopes()
                    ->with('tenant')
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->first();

                if ($membership && $membership->tenant) {
                    $tenant = $membership->tenant;
                    $this->context->setActiveMembership($membership);
                }
            }
        }

        if ($tenant) {
            $this->validateTenantStatus($tenant);
            $this->context->setTenant($tenant);

            // 4. Resolve Branch if X-Branch-ID provided
            $this->resolveBranch($request, $tenant, $user);
        }

        return $tenant;
    }

    /**
     * Resolve tenant from request hostname (subdomain or custom verified domain).
     */
    protected function resolveFromDomain(Request $request): ?Tenant
    {
        $host = strtolower($request->getHost());

        // Ignore standard localhost / IP addresses
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0']) || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Check custom domain mapping
        $customDomain = TenantDomain::where('domain', $host)
            ->where('is_verified', true)
            ->first();

        if ($customDomain && $customDomain->tenant) {
            return $customDomain->tenant;
        }

        // Check subdomain pattern: {subdomain}.{domain}.{tld}
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            $reserved = ['www', 'api', 'admin', 'app', 'platform', 'mail', 'staging', 'dev'];

            if (!in_array($subdomain, $reserved)) {
                $tenant = Tenant::where('slug', $subdomain)->first();
                if ($tenant) {
                    return $tenant;
                }
            }
        }

        return null;
    }

    /**
     * Validate tenant status (active, trial, suspended, cancelled).
     */
    public function validateTenantStatus(Tenant $tenant): void
    {
        if ($tenant->status === 'suspended') {
            throw new TenantSuspendedException(
                $tenant->suspension_reason ?: 'This school account has been suspended. Please contact platform support.'
            );
        }

        if ($tenant->status === 'cancelled' || $tenant->status === 'inactive') {
            throw new TenantInactiveException('This school account is inactive.');
        }

        if ($tenant->status === 'in_trial' && $tenant->trial_ends_at && $tenant->trial_ends_at->isPast()) {
            throw new TenantInactiveException('The free trial period for this school has expired.');
        }
    }

    /**
     * Resolve and validate branch access.
     */
    protected function resolveBranch(Request $request, Tenant $tenant, ?User $user): ?SchoolBranch
    {
        $branchId = $request->header('X-Branch-ID') ?: $request->header('x-branch-id');

        if (!$branchId) {
            // If user's membership is tied to a specific branch, default to it
            $membership = $this->context->getActiveMembership();
            if ($membership && $membership->branch_id) {
                $branch = SchoolBranch::find($membership->branch_id);
                if ($branch) {
                    $this->context->setBranch($branch);
                    return $branch;
                }
            }
            return null;
        }

        $branch = SchoolBranch::where('id', $branchId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$branch) {
            throw new BranchAccessDeniedException("Branch with ID '{$branchId}' does not belong to this school organization.");
        }

        // Validate branch permission if authenticated
        if ($user && !$user->is_platform_admin) {
            $membership = $this->context->getActiveMembership();

            // If membership is strictly bound to another branch
            if ($membership && $membership->branch_id && $membership->branch_id !== $branch->id && !$membership->is_owner) {
                throw new BranchAccessDeniedException("You do not have permission to access branch '{$branch->name}'.");
            }
        }

        $this->context->setBranch($branch);

        if ($branch->school) {
            $this->context->setSchool($branch->school);
        }

        return $branch;
    }
}

