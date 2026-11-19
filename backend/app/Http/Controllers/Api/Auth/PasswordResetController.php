<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Generate password reset token and send notification link.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email')));
        $throttleKey = 'pwd_reset|' . $email;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => "Too many password reset attempts. Please wait {$seconds} seconds before trying again.",
                'timestamp' => now()->toIso8601String(),
            ], 429);
        }

        RateLimiter::hit($throttleKey, 300);

        $user = User::where('email', $email)->first();

        // Security best practice: Don't reveal if user does not exist
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'If this email is registered, a password reset link has been dispatched.',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $plainToken = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($plainToken),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'A password reset token has been generated.',
            'reset_token' => $plainToken, // Returned for API/testing convenience
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Verify reset token and update user password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email')));
        $token = $request->input('token');

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !Hash::check($token, $record->token)) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_RESET_TOKEN',
                'message' => 'The provided password reset token is invalid or expired.',
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        // Check 60 minute expiry
        $createdAt = \Illuminate\Support\Carbon::parse($record->created_at);
        if ($createdAt->diffInMinutes(now()) > 60) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'success' => false,
                'code' => 'EXPIRED_RESET_TOKEN',
                'message' => 'The password reset token has expired. Please request a new one.',
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        // Revoke all existing access tokens
        $user->tokens()->delete();

        // Delete used reset token
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please log in with your new password.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
