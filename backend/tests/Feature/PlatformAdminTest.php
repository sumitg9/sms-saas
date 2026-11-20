<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app(TenantContext::class)->reset();

    $this->platformAdmin = User::factory()->create([
        'email' => 'superadmin-' . uniqid() . '@platform.test',
        'is_platform_admin' => true,
    ]);

    $this->regularUser = User::factory()->create([
        'email' => 'regular-' . uniqid() . '@school.test',
        'is_platform_admin' => false,
    ]);

    $this->testTenant = Tenant::create([
        'name' => 'Trial Academy ' . uniqid(),
        'slug' => 'trial-acad-' . uniqid(),
        'status' => 'trial',
        'trial_ends_at' => now()->addDays(5),
        'contact_email' => 'contact@trial-acad.test',
        'contact_phone' => '+919876543210',
    ]);
});

test('platform super admin can list tenants across SaaS platform', function () {
    Sanctum::actingAs($this->platformAdmin);

    $response = $this->getJson('/api/v1/platform/tenants');

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure(['data', 'meta']);
});

test('platform super admin can suspend and reactivate tenant', function () {
    Sanctum::actingAs($this->platformAdmin);

    // Suspend
    $suspendRes = $this->putJson("/api/v1/platform/tenants/{$this->testTenant->id}/status", [
        'status' => 'suspended',
        'suspension_reason' => 'Non-payment of subscription',
    ]);

    $suspendRes->assertStatus(200)
        ->assertJson(['success' => true]);

    expect($this->testTenant->fresh()->status)->toBe('suspended');

    // Reactivate
    $activateRes = $this->putJson("/api/v1/platform/tenants/{$this->testTenant->id}/status", [
        'status' => 'active',
    ]);

    $activateRes->assertStatus(200);
    expect($this->testTenant->fresh()->status)->toBe('active');
});

test('platform super admin can extend tenant trial period', function () {
    Sanctum::actingAs($this->platformAdmin);

    $oldTrialEnd = $this->testTenant->trial_ends_at;

    $response = $this->postJson("/api/v1/platform/tenants/{$this->testTenant->id}/extend-trial", [
        'days' => 14,
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    expect($this->testTenant->fresh()->trial_ends_at->isAfter($oldTrialEnd))->toBeTrue();
});

