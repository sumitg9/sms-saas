<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\DomainVerificationService;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantResolver;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app(TenantContext::class)->reset();

    $this->tenant = Tenant::create([
        'name' => 'St Mary High School ' . uniqid(),
        'slug' => 'stmarys-' . uniqid(),
        'status' => 'active',
        'contact_email' => 'admin@stmarys.test',
        'contact_phone' => '+919876543210',
    ]);

    $this->user = User::factory()->create(['email' => 'domain-admin-' . uniqid() . '@stmarys.test']);
    TenantMembership::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'roles' => ['school_admin'],
        'is_active' => true,
    ]);
});

test('tenant can register a custom domain and receives DNS instructions', function () {
    Sanctum::actingAs($this->user);

    $domainName = 'portal.stmarys-' . uniqid() . '.edu';

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/settings/domains', [
            'domain' => $domainName,
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'domain' => $domainName,
                'is_verified' => false,
                'ssl_status' => 'pending_dns',
            ],
        ])
        ->assertJsonStructure([
            'data' => [
                'dns_instructions' => ['txt_record', 'cname_record'],
            ],
        ]);

    $this->assertDatabaseHas('tenant_domains', [
        'tenant_id' => $this->tenant->id,
        'domain' => $domainName,
        'is_verified' => false,
    ]);
});

test('reserved platform domains cannot be registered as custom domains', function () {
    Sanctum::actingAs($this->user);

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/settings/domains', [
            'domain' => 'api.example.com',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['domain']);

    $responsePlatform = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/settings/domains', [
            'domain' => 'platform.example.com',
        ]);

    $responsePlatform->assertStatus(422)
        ->assertJsonValidationErrors(['domain']);
});

test('custom domain can be verified and activated', function () {
    Sanctum::actingAs($this->user);

    $domainName = 'academics.stmarys-' . uniqid() . '.org';
    $domain = TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => $domainName,
        'is_verified' => false,
        'verification_token' => 'erp-verify-' . uniqid(),
        'ssl_status' => 'pending_dns',
    ]);

    // Simulate DNS match header for test environment
    $response = $this->withHeaders([
        'X-Tenant-ID' => $this->tenant->id,
        'X-Simulate-DNS-Match' => 'true',
    ])->postJson("/api/v1/settings/domains/{$domain->id}/verify");

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'is_verified' => true,
                'ssl_status' => 'active',
            ],
        ]);

    expect($domain->fresh()->is_verified)->toBeTrue();
});

test('unverified domain cannot be set as primary', function () {
    Sanctum::actingAs($this->user);

    $domain = TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'unverified-' . uniqid() . '.edu',
        'is_verified' => false,
        'verification_token' => 'token-' . uniqid(),
    ]);

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->putJson("/api/v1/settings/domains/{$domain->id}/primary");

    $response->assertStatus(422);
});

test('verified custom domain is resolved by TenantResolver', function () {
    $domainName = 'portal.live-' . uniqid() . '.edu';
    $domain = TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => $domainName,
        'is_verified' => true,
        'is_primary' => true,
        'verification_token' => 'token-123',
    ]);

    $request = Request::create("https://{$domainName}/api/v1/health");
    $context = new TenantContext();
    $resolver = new TenantResolver($context);

    $resolvedTenant = $resolver->resolve($request);

    expect($resolvedTenant)->not->toBeNull();
    expect($resolvedTenant->id)->toBe($this->tenant->id);
    expect($context->getTenantId())->toBe($this->tenant->id);
});

test('subdomain school1.example.com is resolved to matching tenant', function () {
    $slug = 'school-' . rand(1000, 9999);
    $schoolTenant = Tenant::create([
        'name' => 'School ' . $slug,
        'slug' => $slug,
        'status' => 'active',
        'contact_email' => 'admin@' . $slug . '.test',
        'contact_phone' => '+919876543210',
    ]);

    $request = Request::create("https://{$slug}.example.com/api/v1/health");
    $context = new TenantContext();
    $resolver = new TenantResolver($context);

    $resolvedTenant = $resolver->resolve($request);

    expect($resolvedTenant)->not->toBeNull();
    expect($resolvedTenant->id)->toBe($schoolTenant->id);
});

