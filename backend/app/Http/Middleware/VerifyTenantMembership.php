<?php

namespace App\Http\Middleware;

use App\Exceptions\Tenancy\TenantAccessDeniedException;
use App\Models\TenantMembership;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTenantMembership
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

        if (!$user) {
            return $next($request);
        }

        // Platform Super Admin bypasses tenant membership requirements
        if ($user->is_platform_admin) {
            $this->context->setSuperAdmin(true);
            return $next($request);
        }

        $tenantId = $this->context->getTenantId();

        if ($tenantId) {
            $membership = TenantMembership::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();

            if (!$membership) {
                throw new TenantAccessDeniedException(
                    'Access denied: You do not have an active membership in this school organization.'
                );
            }

            $this->context->setActiveMembership($membership);
        }

        return $next($request);
    }
}

