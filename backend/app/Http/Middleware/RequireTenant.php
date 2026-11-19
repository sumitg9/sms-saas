<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenant
{
    public function __construct(
        protected TenantContext $context
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->context->hasTenant() && !$this->context->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'code' => 'TENANT_REQUIRED',
                'message' => 'A valid school tenant context is required to access this resource. Please provide a verified subdomain or X-Tenant-ID header.',
                'timestamp' => now()->toIso8601String(),
            ], 400);
        }

        return $next($request);
    }
}

