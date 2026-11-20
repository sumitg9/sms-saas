<?php

use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app(TenantContext::class)->reset();

    $this->tenant = Tenant::create([
        'name' => 'Setting Test School ' . uniqid(),
        'slug' => 'set-school-' . uniqid(),
        'status' => 'active',
        'contact_email' => 'admin@settings-test.test',
        'contact_phone' => '+919876543210',
    ]);

    $this->school = School::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'SCH-SET',
        'name' => 'Springfield High',
        'education_board' => 'CBSE',
        'email' => 'springfield@test.test',
        'is_active' => true,
    ]);

    $this->branch = SchoolBranch::create([
        'tenant_id' => $this->tenant->id,
        'school_id' => $this->school->id,
        'branch_code' => 'BR-MAIN',
        'name' => 'Main Campus',
        'is_active' => true,
    ]);

    $this->user = User::factory()->create(['email' => 'set-user-' . uniqid() . '@example.com']);
    TenantMembership::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'roles' => ['school_admin'],
        'is_active' => true,
    ]);
});

test('can retrieve school profile', function () {
    Sanctum::actingAs($this->user);

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->getJson('/api/v1/settings/school');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'name' => 'Springfield High',
                'education_board' => 'CBSE',
            ],
        ]);
});

test('can update school profile', function () {
    Sanctum::actingAs($this->user);

    $payload = [
        'name' => 'Springfield International Academy',
        'code' => 'SIA-01',
        'education_board' => 'ICSE',
        'email' => 'info@sia.edu',
        'phone' => '+919876543210',
    ];

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->putJson('/api/v1/settings/school', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'School profile updated successfully.',
            'data' => [
                'name' => 'Springfield International Academy',
                'education_board' => 'ICSE',
            ],
        ]);
});

test('can list and create school branches', function () {
    Sanctum::actingAs($this->user);

    // List
    $listResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->getJson('/api/v1/settings/branches');

    $listResponse->assertStatus(200)
        ->assertJson(['success' => true])
        ->assertJsonCount(1, 'data');

    // Create new branch
    $newBranchPayload = [
        'name' => 'North Campus',
        'branch_code' => 'BR-NORTH',
        'campus_type' => 'secondary',
        'email' => 'north@test.test',
        'is_active' => true,
    ];

    $createResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/settings/branches', $newBranchPayload);

    $createResponse->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'name' => 'North Campus',
                'branch_code' => 'BR-NORTH',
            ],
        ]);

    $this->assertDatabaseHas('school_branches', [
        'tenant_id' => $this->tenant->id,
        'branch_code' => 'BR-NORTH',
    ]);
});

test('can store and retrieve tenant operational settings', function () {
    Sanctum::actingAs($this->user);

    $payload = [
        'settings' => [
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'academic_start_month' => 'April',
        ],
    ];

    $postResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->postJson('/api/v1/settings/operational', $payload);

    $postResponse->assertStatus(200)
        ->assertJson(['success' => true]);

    $getResponse = $this->withHeaders(['X-Tenant-ID' => $this->tenant->id])
        ->getJson('/api/v1/settings/operational');

    $getResponse->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => [
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'academic_start_month' => 'April',
            ],
        ]);
});
