<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected Student $student;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'National Public Academy',
            'slug' => 'npa-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@npa.edu',
            'contact_phone' => '+919876543299',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'NPA Main Campus',
            'code' => 'NPA-' . rand(100, 999),
        ]);

        $this->branch = SchoolBranch::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'High School Block',
            'branch_code' => 'HSB',
            'is_main_branch' => true,
        ]);

        $tenantContext = app(TenantContext::class);
        $tenantContext->setTenant($this->tenant);
        $tenantContext->setSchool($this->school);
        $tenantContext->setBranch($this->branch);

        $this->session = AcademicSession::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
            'status' => 'active',
        ]);

        $this->class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Grade 12',
            'code' => 'G12',
            'display_order' => 12,
        ]);

        $this->section = Section::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'name' => 'Section Science',
            'capacity' => 45,
        ]);

        // Student & Guardian
        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'NPA-2026-99',
            'first_name' => 'Rohan',
            'last_name' => 'Deshmukh',
            'admission_date' => '2026-04-01',
            'date_of_birth' => '2008-09-14',
            'gender' => 'male',
            'status' => 'active',
        ]);

        StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => 18,
            'enrollment_date' => '2026-04-01',
            'status' => 'active',
        ]);

        $guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Sanjay',
            'last_name' => 'Deshmukh',
            'relation_type' => 'father',
            'phone' => '+919876543201',
        ]);

        $this->student->guardians()->attach($guardian->id, [
            'tenant_id' => $this->tenant->id,
            'is_primary' => true,
        ]);

        // Admin User
        $adminRole = Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'web'], ['display_name' => 'School Admin']);

        $this->adminUser = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Officer',
            'email' => 'admin-' . Str::random(6) . '@npa.edu',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->assignRole($adminRole);

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'branch_id' => $this->branch->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);
    }

    public function test_can_create_custom_certificate_template(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/certificates/templates', [
            'type' => 'merit',
            'title' => 'Certificate of Academic Excellence',
            'body_template' => 'This is to honor {{student_name}} of {{class_name}} for securing top rank in {{academic_year}}.',
            'signature_title_1' => 'Head of Science',
            'signature_title_2' => 'Principal',
        ], [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Certificate template created successfully.',
                'data' => [
                    'type' => 'merit',
                    'title' => 'Certificate of Academic Excellence',
                ],
            ]);

        $this->assertDatabaseHas('certificate_templates', [
            'tenant_id' => $this->tenant->id,
            'type' => 'merit',
        ]);
    }

    public function test_can_issue_certificate_with_placeholders_and_verification_code(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/certificates/issue', [
            'student_id' => $this->student->id,
            'branch_id' => $this->branch->id,
            'type' => 'transfer',
            'conduct' => 'Outstanding',
            'reason' => 'Relocation to Mumbai',
        ], [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Certificate issued successfully.',
            ]);

        $cert = $response->json('data');
        $this->assertNotNull($cert['certificate_number']);
        $this->assertStringStartsWith('CERT-HSB-TRANSFER-', $cert['certificate_number']);
        $this->assertNotNull($cert['verification_code']);
        $this->assertStringStartsWith('VER-', $cert['verification_code']);

        // Check dynamic placeholder rendering in content
        $content = $cert['rendered_content'];
        $this->assertStringContainsString('Rohan Deshmukh', $content);
        $this->assertStringContainsString('Sanjay Deshmukh', $content);
        $this->assertStringContainsString('Grade 12', $content);
        $this->assertStringContainsString('Outstanding', $content);
        $this->assertStringContainsString('Relocation to Mumbai', $content);

        // Check Audit Log
        $audit = AuditLog::where('action', 'certificate_issued')
            ->where('auditable_id', $cert['id'])
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($cert['certificate_number'], $audit->new_values['certificate_number']);
    }

    public function test_can_render_printable_html_certificate(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Issue certificate first
        $issueResponse = $this->postJson('/api/v1/certificates/issue', [
            'student_id' => $this->student->id,
            'branch_id' => $this->branch->id,
            'type' => 'bonafide',
        ], [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $certId = $issueResponse->json('data.id');

        $printResponse = $this->get('/api/v1/certificates/' . $certId . '/print', [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $printResponse->assertStatus(200);
        $printResponse->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $html = $printResponse->getContent();
        $this->assertStringContainsString('Bonafide Certificate', $html);
        $this->assertStringContainsString('OFFICIAL ACADEMIC CREDENTIAL', $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    public function test_public_qr_code_verification_works_without_authentication(): void
    {
        Sanctum::actingAs($this->adminUser);

        $issueResponse = $this->postJson('/api/v1/certificates/issue', [
            'student_id' => $this->student->id,
            'branch_id' => $this->branch->id,
            'type' => 'character',
        ], [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $verificationCode = $issueResponse->json('data.verification_code');

        // Verify anonymously without any Sanctum session
        $this->app['auth']->forgetGuards();

        $verifyResponse = $this->getJson('/api/v1/certificates/verify/' . $verificationCode);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'is_valid' => true,
                'recipient_name' => 'Rohan Deshmukh',
                'type' => 'character',
                'status' => 'verified_authentic',
            ]);

        // Negative check: Invalid code returns 404
        $invalidResponse = $this->getJson('/api/v1/certificates/verify/NON-EXISTENT-CODE');
        $invalidResponse->assertStatus(404)
            ->assertJson([
                'is_valid' => false,
            ]);
    }

    public function test_can_revoke_certificate_and_public_verification_reflects_revocation(): void
    {
        Sanctum::actingAs($this->adminUser);

        $issueResponse = $this->postJson('/api/v1/certificates/issue', [
            'student_id' => $this->student->id,
            'branch_id' => $this->branch->id,
            'type' => 'study',
        ], [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $certId = $issueResponse->json('data.id');
        $verificationCode = $issueResponse->json('data.verification_code');

        // Revoke with reason
        $revokeResponse = $this->postJson('/api/v1/certificates/' . $certId . '/revoke', [
            'reason' => 'Issued mistakenly due to duplicate record',
        ], [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $revokeResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Certificate has been revoked.',
                'data' => [
                    'id' => $certId,
                    'status' => 'revoked',
                    'revocation_reason' => 'Issued mistakenly due to duplicate record',
                ],
            ]);

        // Audit Log for revocation
        $audit = AuditLog::where('action', 'certificate_revoked')
            ->where('auditable_id', $certId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Issued mistakenly due to duplicate record', $audit->new_values['reason']);

        // Public verification now returns 410 Gone / Revoked warning
        $this->app['auth']->forgetGuards();

        $verifyResponse = $this->getJson('/api/v1/certificates/verify/' . $verificationCode);
        $verifyResponse->assertStatus(410)
            ->assertJson([
                'is_valid' => false,
                'is_revoked' => true,
                'revocation_reason' => 'Issued mistakenly due to duplicate record',
            ]);
    }
}
