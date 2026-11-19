<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $tfaService,
        protected AuthController $authController
    ) {}

    /**
     * Generate a new 2FA secret and recovery codes for the authenticated user.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_confirmed_at !== null) {
            return response()->json([
                'success' => false,
                'code' => 'TWO_FACTOR_ALREADY_ENABLED',
                'message' => 'Two-factor authentication is already enabled for this account.',
                'timestamp' => now()->toIso8601String(),
            ], 400);
        }

        $secret = $this->tfaService->generateSecret();
        $recoveryCodes = $this->tfaService->generateRecoveryCodes();

        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => null, // Unconfirmed until verified
        ]);

        $otpAuthUri = $this->tfaService->getOtpAuthUri('EduCore School ERP', $user->email, $secret);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication secret generated. Please confirm with a 6-digit code.',
            'data' => [
                'secret' => $secret,
                'otpauth_uri' => $otpAuthUri,
                'recovery_codes' => $recoveryCodes,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Confirm 2FA setup by verifying a 6-digit TOTP code.
     */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'code' => 'TWO_FACTOR_NOT_INITIALIZED',
                'message' => 'Two-factor authentication has not been initiated. Please call enable first.',
                'timestamp' => now()->toIso8601String(),
            ], 400);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        if (!$this->tfaService->verifyCode($secret, $request->input('code'))) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_TWO_FACTOR_CODE',
                'message' => 'The provided two-factor code is invalid or expired.',
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $user->update([
            'two_factor_confirmed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication successfully confirmed and activated.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Disable 2FA with password confirmation.
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_PASSWORD',
                'message' => 'The provided password is incorrect.',
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication has been disabled.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Verify 2FA challenge code or backup recovery code during login.
     */
    public function challenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        try {
            $payload = json_decode(Crypt::decryptString($request->input('two_factor_token')), true);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_TWO_FACTOR_TOKEN',
                'message' => 'Invalid or malformed two-factor session token.',
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        if (empty($payload['user_id']) || $payload['expires_at'] < now()->timestamp) {
            return response()->json([
                'success' => false,
                'code' => 'TWO_FACTOR_SESSION_EXPIRED',
                'message' => 'Two-factor authentication session expired. Please log in again.',
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $user = User::find($payload['user_id']);
        if (!$user || !$user->two_factor_secret || !$user->two_factor_confirmed_at) {
            return response()->json([
                'success' => false,
                'code' => 'TWO_FACTOR_NOT_CONFIGURED',
                'message' => 'User does not have 2FA configured.',
                'timestamp' => now()->toIso8601String(),
            ], 400);
        }

        $verified = false;

        // 1. Check recovery code if provided
        if ($request->filled('recovery_code')) {
            $inputRecovery = trim($request->input('recovery_code'));
            $codes = $user->two_factor_recovery_codes ?: [];

            foreach ($codes as $index => $code) {
                if (hash_equals($code, $inputRecovery)) {
                    // Remove used single-use recovery code
                    unset($codes[$index]);
                    $user->update(['two_factor_recovery_codes' => array_values($codes)]);
                    $verified = true;
                    break;
                }
            }
        } elseif ($request->filled('code')) {
            // 2. Check 6-digit TOTP code
            $secret = Crypt::decryptString($user->two_factor_secret);
            $verified = $this->tfaService->verifyCode($secret, $request->input('code'));
        }

        if (!$verified) {
            LoginHistory::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed_2fa',
                'failure_reason' => 'Invalid two-factor code or recovery code.',
            ]);

            return response()->json([
                'success' => false,
                'code' => 'INVALID_TWO_FACTOR_CODE',
                'message' => 'The provided code or recovery code is incorrect.',
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        return $this->authController->issueLoginResponse($user, $request);
    }
}

