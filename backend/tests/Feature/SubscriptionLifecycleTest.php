<?php

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app(TenantContext::class)->reset();

    $this->tenant = Tenant::create([
        'name' => 'SaaS Subscription Test ' . uniqid(),
        'slug' => 'saas-sub-' . uniqid(),
        'status' => 'trial',
        'trial_ends_at' => now()->addDays(14),
        'contact_email' => 'billing@saas-test.test',
        'contact_phone' => '+919876543210',
    ]);

    $this->user = User::factory()->create(['email' => 'sub-user-' . uniqid() . '@example.com']);
    TenantMembership::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'roles' => ['school_admin'],
        'is_active' => true,
    ]);

    $this->plan = SubscriptionPlan::create([
        'name' => 'Growth Plan',
        'slug' => 'growth-' . uniqid(),
        'monthly_price' => 4999.00,
        'annual_price' => 49990.00,
        'currency' => 'INR',
        'max_students' => 1000,
        'max_staff' => 100,
        'max_branches' => 3,
        'storage_limit_gb' => 50,
        'is_active' => true,
    ]);
});

test('public endpoint returns available subscription plans', function () {
    $response = $this->getJson('/api/v1/subscriptions/plans');

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonFragment(['name' => 'Growth Plan']);
});

test('tenant can fetch current subscription details and usage', function () {
    Sanctum::actingAs($this->user);

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->getJson('/api/v1/subscriptions/current');

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'data' => [
                'tenant',
                'usage' => ['students', 'staff', 'branches'],
            ],
        ]);
});

test('tenant can subscribe to a plan and invoice is generated', function () {
    Sanctum::actingAs($this->user);

    $payload = [
        'plan_id' => $this->plan->id,
        'billing_cycle' => 'monthly',
        'payment_method' => 'card',
    ];

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/subscriptions/subscribe', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Subscription activated successfully.',
        ]);

    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
    ]);

    $this->assertDatabaseHas('subscription_invoices', [
        'tenant_id' => $this->tenant->id,
        'status' => 'paid',
        'total_amount' => 4999.00,
    ]);

    $this->assertDatabaseHas('subscription_payments', [
        'tenant_id' => $this->tenant->id,
        'amount' => 4999.00,
        'status' => 'success',
    ]);

    // Check tenant is active
    expect($this->tenant->fresh()->status)->toBe('active');
});

test('tenant can cancel subscription', function () {
    Sanctum::actingAs($this->user);

    // Subscribe first
    $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/subscriptions/subscribe', [
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'annual',
        ]);

    // Now cancel
    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/subscriptions/cancel');

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $this->tenant->id,
        'status' => 'cancelled',
    ]);
});

test('tenant can retrieve invoices history', function () {
    Sanctum::actingAs($this->user);

    $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/subscriptions/subscribe', [
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
        ]);

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->getJson('/api/v1/subscriptions/invoices');

    $response->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonCount(1, 'data');
});
