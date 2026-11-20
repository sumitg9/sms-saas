<?php

use App\Models\FileUpload;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    app(TenantContext::class)->reset();
    Storage::fake('public');

    $this->tenantA = Tenant::create([
        'name' => 'School A ' . uniqid(),
        'slug' => 'school-a-' . uniqid(),
        'status' => 'active',
        'contact_email' => 'admin-a@test.test',
        'contact_phone' => '+919876543210',
    ]);

    $this->tenantB = Tenant::create([
        'name' => 'School B ' . uniqid(),
        'slug' => 'school-b-' . uniqid(),
        'status' => 'active',
        'contact_email' => 'admin-b@test.test',
        'contact_phone' => '+919876543211',
    ]);

    $this->userA = User::factory()->create(['email' => 'user-a-' . uniqid() . '@test.test']);
    TenantMembership::create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->userA->id,
        'roles' => ['school_admin'],
        'is_active' => true,
    ]);

    $this->userB = User::factory()->create(['email' => 'user-b-' . uniqid() . '@test.test']);
    TenantMembership::create([
        'tenant_id' => $this->tenantB->id,
        'user_id' => $this->userB->id,
        'roles' => ['school_admin'],
        'is_active' => true,
    ]);
});

test('authenticated user can upload allowed file within tenant directory', function () {
    Sanctum::actingAs($this->userA);

    $file = UploadedFile::fake()->create('report_card.pdf', 500, 'application/pdf');

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenantA->id])
        ->postJson('/api/v1/uploads', [
            'file' => $file,
            'folder' => 'documents',
        ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'message' => 'File uploaded successfully',
            'data' => [
                'original_name' => 'report_card.pdf',
                'mime_type' => 'application/pdf',
            ],
        ]);

    $fileId = $response->json('data.id');
    $this->assertDatabaseHas('file_uploads', [
        'id' => $fileId,
        'tenant_id' => $this->tenantA->id,
        'original_name' => 'report_card.pdf',
    ]);
});

test('executable or unauthorized file types are rejected by upload validator', function () {
    Sanctum::actingAs($this->userA);

    $badFile = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');

    $response = $this->withHeaders(['X-Tenant-ID' => $this->tenantA->id])
        ->postJson('/api/v1/uploads', [
            'file' => $badFile,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('user from Tenant B cannot access or download private files from Tenant A', function () {
    // 1. User A uploads a file
    Sanctum::actingAs($this->userA);
    $file = UploadedFile::fake()->create('confidential.pdf', 200, 'application/pdf');
    $uploadRes = $this->withHeaders(['X-Tenant-ID' => $this->tenantA->id])
        ->postJson('/api/v1/uploads', [
            'file' => $file,
            'is_private' => true,
        ]);

    $fileId = $uploadRes->json('data.id');

    // 2. User B tries to download the file using Tenant B's context
    Sanctum::actingAs($this->userB);
    $downloadRes = $this->withHeaders(['X-Tenant-ID' => $this->tenantB->id])
        ->getJson("/api/v1/uploads/{$fileId}");

    // TenantScoped or FileUploadPolicy rejects with 404 or 403
    expect(in_array($downloadRes->status(), [403, 404], true))->toBeTrue();
});

