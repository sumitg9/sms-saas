<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Auth\TwoFactorAuthService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app(TenantContext::class)->reset();
});

test('user can register with school organization, creating tenant, branch, and owner role', function () {
    $email = 'john-' . uniqid() . '@example.com';
    $payload = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => $email,
        'phone' => '98' . rand(10000000, 99999999),
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'school_name' => 'Stellar International',
        'organization_type' => 'private',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'token',
            'data' => [
                'user' => ['id', 'email', 'name'],
                'active_tenant' => ['id', 'name'],
                'roles',
                'permissions',
            ],
        ]);

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull()
        ->and($user->memberships)->toHaveCount(1)
        ->and($user->memberships->first()->is_owner)->toBeTrue()
        ->and($user->hasRole('school_owner'))->toBeTrue();
});

test('user login validates credentials and records login history', function () {
    $email = 'teacher-' . uniqid() . '@example.com';
    $user = User::factory()->create([
        'email' => $email,
        'password' => Hash::make('SecretPass123!'),
        'is_active' => true,
    ]);

    // 1. Invalid password attempt
    $invalidResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'WrongPassword!',
    ]);

    $invalidResponse->assertStatus(422)
        ->assertJsonFragment(['code' => 'INVALID_CREDENTIALS']);

    // Check failed login logged
    $this->assertDatabaseHas('login_histories', [
        'email' => $email,
        'status' => 'failed_password',
    ]);

    // 2. Successful login attempt
    $successResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'SecretPass123!',
        'device_name' => 'MacBook Pro',
    ]);

    $successResponse->assertOk()
        ->assertJsonStructure(['success', 'token', 'data' => ['user']]);

    // Check success login logged
    $this->assertDatabaseHas('login_histories', [
        'email' => $email,
        'status' => 'success',
    ]);

    // Check session tracked
    expect(UserSession::where('user_id', $user->id)->exists())->toBeTrue();
});

test('deactivated account cannot log in', function () {
    $email = 'inactive-' . uniqid() . '@example.com';
    $user = User::factory()->create([
        'email' => $email,
        'password' => Hash::make('SecretPass123!'),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'SecretPass123!',
    ]);

    $response->assertStatus(403)
        ->assertJsonFragment(['code' => 'ACCOUNT_DEACTIVATED']);
});

test('brute force login is throttled after 5 failed attempts', function () {
    $email = 'target-' . uniqid() . '@example.com';
    $user = User::factory()->create([
        'email' => $email,
        'password' => Hash::make('RealPassword123!'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'WrongPassword',
        ]);
    }

    // 6th attempt should be throttled
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'WrongPassword',
    ]);

    $response->assertStatus(429)
        ->assertJsonFragment(['code' => 'RATE_LIMIT_EXCEEDED']);
});

test('user can log out and revoke active access token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonFragment(['success' => true]);

    // Token was revoked
    expect($user->tokens()->count())->toBe(0);
});

test('password reset flow generates token and resets password securely', function () {
    $email = 'resetme-' . uniqid() . '@example.com';
    $user = User::factory()->create([
        'email' => $email,
        'password' => Hash::make('OldPassword123!'),
    ]);

    // 1. Request forgot password
    $forgotResponse = $this->postJson('/api/v1/auth/forgot-password', [
        'email' => $email,
    ]);

    $forgotResponse->assertOk()
        ->assertJsonStructure(['success', 'reset_token']);

    $plainToken = $forgotResponse->json('reset_token');

    // 2. Submit reset with token
    $resetResponse = $this->postJson('/api/v1/auth/reset-password', [
        'token' => $plainToken,
        'email' => $email,
        'password' => 'NewPassword999!',
        'password_confirmation' => 'NewPassword999!',
    ]);

    $resetResponse->assertOk()
        ->assertJsonFragment(['success' => true]);

    // Verify user can log in with new password
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'NewPassword999!',
    ]);

    $loginResponse->assertOk();
});

test('authenticated user can change password and other active sessions are revoked', function () {
    $user = User::factory()->create([
        'password' => Hash::make('CurrentPassword123!'),
    ]);

    $token1 = $user->createToken('device1')->plainTextToken;
    $token2 = $user->createToken('device2')->plainTextToken;

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/change-password', [
        'current_password' => 'CurrentPassword123!',
        'password' => 'BrandNewPassword123!',
        'password_confirmation' => 'BrandNewPassword123!',
    ]);

    $response->assertOk();

    // Verify new password is saved
    expect(Hash::check('BrandNewPassword123!', $user->fresh()->password))->toBeTrue();
});

test('session management lists tracked devices and allows remote session revocation', function () {
    $user = User::factory()->create();

    $session1 = UserSession::create([
        'user_id' => $user->id,
        'ip_address' => '192.168.1.10',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'device_type' => 'desktop',
        'last_activity_at' => now(),
    ]);

    $session2 = UserSession::create([
        'user_id' => $user->id,
        'ip_address' => '10.0.0.5',
        'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
        'device_type' => 'mobile',
        'last_activity_at' => now()->subHours(2),
    ]);

    Sanctum::actingAs($user);

    // List sessions
    $listResponse = $this->getJson('/api/v1/auth/sessions');
    $listResponse->assertOk()
        ->assertJsonCount(2, 'data');

    // Revoke mobile session
    $deleteResponse = $this->deleteJson("/api/v1/auth/sessions/{$session2->id}");
    $deleteResponse->assertOk();

    expect(UserSession::where('id', $session2->id)->exists())->toBeFalse()
        ->and(UserSession::where('id', $session1->id)->exists())->toBeTrue();
});

test('two-factor authentication flow with TOTP confirmation and login challenge', function () {
    $email = 'tfa-' . uniqid() . '@example.com';
    $user = User::factory()->create([
        'email' => $email,
        'password' => Hash::make('Password123!'),
        'two_factor_confirmed_at' => null,
    ]);

    Sanctum::actingAs($user);

    // 1. Enable 2FA
    $enableResponse = $this->postJson('/api/v1/auth/2fa/enable');
    $enableResponse->assertOk()
        ->assertJsonStructure(['data' => ['secret', 'otpauth_uri', 'recovery_codes']]);

    $secret = $enableResponse->json('data.secret');
    $recoveryCodes = $enableResponse->json('data.recovery_codes');

    expect($recoveryCodes)->toHaveCount(8);

    // 2. Generate valid TOTP code
    $tfaService = app(TwoFactorAuthService::class);
    // Use reflection to calculate TOTP
    $reflection = new \ReflectionClass($tfaService);
    $method = $reflection->getMethod('calculateTotp');
    $method->setAccessible(true);
    $validCode = $method->invoke($tfaService, $secret, (int) floor(time() / 30));

    // 3. Confirm 2FA
    $confirmResponse = $this->postJson('/api/v1/auth/2fa/confirm', ['code' => $validCode]);
    $confirmResponse->assertOk();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();

    // 4. Log in requires 2FA challenge
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'Password123!',
    ]);

    $loginResponse->assertOk()
        ->assertJsonFragment(['two_factor_required' => true]);

    $tempToken = $loginResponse->json('two_factor_token');

    // 5. Complete login challenge with backup recovery code
    $challengeResponse = $this->postJson('/api/v1/auth/2fa/challenge', [
        'two_factor_token' => $tempToken,
        'recovery_code' => $recoveryCodes[0],
    ]);

    $challengeResponse->assertOk()
        ->assertJsonStructure(['token', 'data' => ['user']]);

    // Verify recovery code was consumed
    $freshUser = $user->fresh();
    expect($freshUser->two_factor_recovery_codes)->toHaveCount(7)
        ->and($freshUser->two_factor_recovery_codes)->not->toContain($recoveryCodes[0]);
});

test('granular permissions restrict access and role middleware enforces authorization', function () {
    $tenant = Tenant::factory()->create();
    $school = School::create(['tenant_id' => $tenant->id, 'name' => 'City School', 'code' => 'CS', 'is_active' => true]);
    $branch = SchoolBranch::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'name' => 'Main', 'branch_code' => 'CS-1', 'is_active' => true]);

    $userWithoutPerms = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $userWithoutPerms->id, 'is_active' => true]);

    $userWithPerms = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $userWithPerms->id, 'is_active' => true]);

    $adminRole = Role::where('name', 'school_admin')->first();
    $userWithPerms->assignRole($adminRole, $tenant->id, $branch->id);

    // 1. User without permissions is blocked with 403
    Sanctum::actingAs($userWithoutPerms);

    $blocked = $this->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->getJson('/api/v1/students');

    $blocked->assertStatus(403)
        ->assertJsonFragment(['code' => 'FORBIDDEN_PERMISSION_DENIED']);

    // 2. User with permissions is authorized
    Sanctum::actingAs($userWithPerms);

    $allowed = $this->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->getJson('/api/v1/students');

    $allowed->assertOk();
});

test('privilege escalation is prevented when assigning roles', function () {
    $tenant = Tenant::factory()->create();
    $school = School::create(['tenant_id' => $tenant->id, 'name' => 'Campus School', 'code' => 'CS', 'is_active' => true]);
    $branch = SchoolBranch::create(['tenant_id' => $tenant->id, 'school_id' => $school->id, 'name' => 'Campus', 'branch_code' => 'BR', 'is_active' => true]);

    $normalAdmin = User::factory()->create(['is_platform_admin' => false]);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $normalAdmin->id, 'is_active' => true]);
    $adminRole = Role::where('name', 'school_admin')->first();
    $normalAdmin->assignRole($adminRole, $tenant->id, $branch->id);

    $targetUser = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $targetUser->id, 'is_active' => true]);

    Sanctum::actingAs($normalAdmin);

    // Normal admin attempts to assign Platform Super Admin role
    $escalateResponse = $this->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson("/api/v1/users/{$targetUser->id}/roles", [
            'role_name' => 'platform_admin',
        ]);

    $escalateResponse->assertStatus(403)
        ->assertJsonFragment(['code' => 'PRIVILEGE_ESCALATION_DENIED']);

    // Normal admin CAN assign regular school roles within their tenant
    $legitResponse = $this->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->postJson("/api/v1/users/{$targetUser->id}/roles", [
            'role_name' => 'teacher',
        ]);

    $legitResponse->assertOk()
        ->assertJsonFragment(['success' => true]);

    expect($targetUser->hasRole('teacher', $tenant->id))->toBeTrue();
});
