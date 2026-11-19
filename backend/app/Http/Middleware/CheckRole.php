<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
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

        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'code' => 'FORBIDDEN_ROLE_REQUIRED',
            'message' => 'User does not possess any of the required roles: ' . implode(', ', $roles),
            'required_roles' => $roles,
            'timestamp' => now()->toIso8601String(),
        ], 403);
    }
}

