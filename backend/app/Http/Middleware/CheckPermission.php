<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication is required to perform this action.',
                'timestamp' => now()->toIso8601String(),
            ], 401);
        }

        if ($user->is_platform_admin) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'code' => 'FORBIDDEN_PERMISSION_DENIED',
            'message' => 'User does not possess the required permission: ' . implode(', ', $permissions),
            'required_permissions' => $permissions,
            'timestamp' => now()->toIso8601String(),
        ], 403);
    }
}

