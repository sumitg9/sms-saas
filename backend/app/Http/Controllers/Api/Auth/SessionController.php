<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * List all tracked active device sessions for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentIp = $request->ip();
        $currentAgent = $request->userAgent();

        $sessions = UserSession::where('user_id', $user->id)
            ->orderBy('last_activity_at', 'desc')
            ->get()
            ->map(function ($s) use ($currentIp, $currentAgent) {
                return [
                    'id' => $s->id,
                    'ip_address' => $s->ip_address,
                    'user_agent' => $s->user_agent,
                    'device_type' => $s->device_type,
                    'last_activity_at' => $s->last_activity_at->toIso8601String(),
                    'is_current' => ($s->ip_address === $currentIp && $s->user_agent === $currentAgent),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Revoke a specific tracked device session.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $deleted = UserSession::where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'code' => 'SESSION_NOT_FOUND',
                'message' => 'The specified session was not found.',
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session successfully revoked.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Revoke all other sessions and tokens except the active one.
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentIp = $request->ip();
        $currentAgent = $request->userAgent();

        // Delete other sessions from user_sessions
        UserSession::where('user_id', $user->id)
            ->where(function ($q) use ($currentIp, $currentAgent) {
                $q->where('ip_address', '!=', $currentIp)
                    ->orWhere('user_agent', '!=', $currentAgent);
            })
            ->delete();

        // Revoke all other Sanctum tokens
        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'All other sessions and devices have been logged out.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

