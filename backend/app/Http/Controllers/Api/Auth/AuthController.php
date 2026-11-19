<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Authenticate user, handle 2FA challenges, track sessions and login history.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email')));
        $throttleKey = Str::transliterate($email . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            LoginHistory::create([
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'locked_out',
                'failure_reason' => "Too many failed login attempts. Throttled for {$seconds} seconds.",
            ]);

            return response()->json([
                'success' => false,
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => "Too many failed login attempts. Please try again in {$seconds} seconds.",
                'retry_after' => $seconds,
                'timestamp' => now()->toIso8601String(),
            ], 429);
        }

        $user = User::where('email', $email)->first();

        // Check password validity
        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            LoginHistory::create([
                'user_id' => $user?->id,
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed_password',
                'failure_reason' => 'Invalid email or password.',
            ]);

            return response()->json([
                'success' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'The provided credentials do not match our records.',
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        // Check account activation
        if (!$user->is_active) {
            LoginHistory::create([
                'user_id' => $user->id,
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'failed',
                'failure_reason' => 'Account has been deactivated.',
            ]);

            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_DEACTIVATED',
                'message' => 'Your account has been deactivated. Please contact your school administrator.',
                'timestamp' => now()->toIso8601String(),
            ], 403);
        }

        // Two-Factor Authentication Check
        if ($user->two_factor_confirmed_at !== null) {
            $tempToken = Crypt::encryptString(json_encode([
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ]));

            LoginHistory::create([
                'user_id' => $user->id,
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => '2fa_pending',
            ]);

            return response()->json([
                'success' => true,
                'two_factor_required' => true,
                'message' => 'Two-factor authentication code required.',
                'two_factor_token' => $tempToken,
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        RateLimiter::clear($throttleKey);

        return $this->issueLoginResponse($user, $request);
    }

    /**
     * Register a new user and optionally initialize a demo school organization.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'] ?? null,
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        // If registering a school, create tenant and branch
        if (!empty($validated['school_name'])) {
            $slug = Str::slug($validated['school_name']) . '-' . Str::lower(Str::random(5));
            $tenant = Tenant::create([
                'name' => $validated['school_name'],
                'slug' => $slug,
                'organization_type' => $validated['organization_type'] ?? 'private',
                'contact_email' => $user->email,
                'contact_phone' => $user->phone ?? '0000000000',
                'status' => 'in_trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            $school = School::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['school_name'] . ' Main Campus',
                'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $validated['school_name']), 0, 4)) . '-MAIN',
                'is_active' => true,
            ]);

            $branch = SchoolBranch::create([
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'name' => 'Main Campus Branch',
                'branch_code' => 'BR-01',
                'campus_type' => 'main',
                'is_main_branch' => true,
                'is_active' => true,
            ]);

            TenantMembership::create([
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'is_owner' => true,
                'is_active' => true,
            ]);

            $ownerRole = Role::firstOrCreate(['name' => 'school_owner'], [
                'display_name' => 'School Owner',
                'is_system_defined' => true,
            ]);

            $user->assignRole($ownerRole, $tenant->id, $branch->id);
            $this->tenantContext->setTenant($tenant);
        }

        return $this->issueLoginResponse($user, $request, 201);
    }

    /**
     * Terminate the current authenticated session.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            // Revoke current Sanctum token
            $user->currentAccessToken()?->delete();

            // Invalidate session in user_sessions table
            UserSession::where('user_id', $user->id)
                ->where('ip_address', $request->ip())
                ->delete();

            // Invalidate web session if stateful
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get details of the currently authenticated user with tenant context and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $this->tenantContext->getTenantId();

        $memberships = TenantMembership::withoutGlobalScopes()
            ->with(['tenant:id,name,slug,status', 'branch:id,name,branch_code'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar_url' => $user->avatar_url,
                    'is_platform_admin' => $user->is_platform_admin,
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                    'email_verified' => $user->email_verified_at !== null,
                ],
                'active_tenant' => [
                    'id' => $this->tenantContext->getTenantId(),
                    'name' => $this->tenantContext->getTenant()?->name,
                    'branch_id' => $this->tenantContext->getBranchId(),
                    'branch_name' => $this->tenantContext->getBranch()?->name,
                ],
                'roles' => $user->getRolesForTenant($tenantId),
                'permissions' => $user->getPermissionsForTenant($tenantId),
                'memberships' => $memberships,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $user->fresh(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Change user password with current password verification.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_CURRENT_PASSWORD',
                'message' => 'The provided current password does not match.',
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        // Revoke all other access tokens except the current one
        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully. Other active sessions have been logged out.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Issue standardized login token, create user session, and record history.
     */
    public function issueLoginResponse(User $user, Request $request, int $status = 200): JsonResponse
    {
        // Update login timestamps
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Log successful login
        LoginHistory::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'success',
        ]);

        // Track active device session
        $userAgent = $request->userAgent() ?: 'Unknown Device';
        $deviceType = 'desktop';
        if (preg_match('/(mobile|android|iphone|ipad)/i', $userAgent)) {
            $deviceType = preg_match('/ipad|tablet/i', $userAgent) ? 'tablet' : 'mobile';
        }

        UserSession::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'last_activity_at' => now(),
        ]);

        // First-party cookie login support
        if ($request->hasSession()) {
            Auth::login($user, (bool) $request->input('remember', false));
        }

        // Generate Sanctum Bearer Token
        $deviceName = $request->input('device_name') ?: ($deviceType . ' client');
        $token = $user->createToken($deviceName)->plainTextToken;

        $tenantId = $this->tenantContext->getTenantId();

        $memberships = TenantMembership::withoutGlobalScopes()
            ->with(['tenant:id,name,slug,status', 'branch:id,name,branch_code'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Authentication successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar_url' => $user->avatar_url,
                    'is_platform_admin' => $user->is_platform_admin,
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                    'email_verified' => $user->email_verified_at !== null,
                ],
                'active_tenant' => [
                    'id' => $this->tenantContext->getTenantId(),
                    'name' => $this->tenantContext->getTenant()?->name,
                    'branch_id' => $this->tenantContext->getBranchId(),
                    'branch_name' => $this->tenantContext->getBranch()?->name,
                ],
                'roles' => $user->getRolesForTenant($tenantId),
                'permissions' => $user->getPermissionsForTenant($tenantId),
                'memberships' => $memberships,
            ],
            'timestamp' => now()->toIso8601String(),
        ], $status);
    }
}
