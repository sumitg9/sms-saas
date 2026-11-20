<?php

namespace Tests\Feature;

use App\Jobs\Reporting\ProcessExportJob;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\ExportJob;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Reporting\ReportingEngineService;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportingEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant & Branch
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'St. Xavier High School',
            'slug' => 'st-xavier-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@stxavier.edu',
            'contact_phone' => '+919876543211',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'St. Xavier Main Campus',
            'code' => 'STX-' . rand(100, 999),
        ]);

        $this->branch = SchoolBranch::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Primary Section',
            'branch_code' => 'PS-' . rand(100, 999),
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
            'name' => 'Grade 5',
            'code' => 'G5',
            'display_order' => 5,
        ]);

        $this->section = Section::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'name' => 'Section A',
            'capacity' => 35,
        ]);

        // User & Role
        $adminRole = Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'web'], ['display_name' => 'School Admin']);

        $this->adminUser = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Officer',
            'email' => 'admin-' . Str::random(6) . '@stxavier.edu',
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

        // Seed 3 students and attendance
        $this->seedReportingData();
    }

    private function seedReportingData(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $student = Student::create([
                'tenant_id' => $this->tenant->id,
                'school_id' => $this->school->id,
                'branch_id' => $this->branch->id,
                'admission_number' => 'STX-' . (1000 + $i),
                'first_name' => 'ReportingStudent' . $i,
                'last_name' => 'Smith',
                'admission_date' => '2026-04-01',
                'date_of_birth' => '2015-02-10',
                'gender' => 'female',
                'status' => 'active',
            ]);

            $enrollment = StudentEnrollment::create([
                'tenant_id' => $this->tenant->id,
                'school_id' => $this->school->id,
                'branch_id' => $this->branch->id,
                'student_id' => $student->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'roll_number' => $i,
                'enrollment_date' => '2026-04-01',
                'status' => 'active',
            ]);

            StudentAttendance::create([
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'academic_session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'date' => Carbon::today(),
                'status' => $i % 2 === 0 ? 'absent' : 'present',
                'remarks' => 'Regular session',
            ]);

            FeeInvoice::create([
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'academic_session_id' => $this->session->id,
                'invoice_number' => 'INV-STX-' . (1000 + $i),
                'subtotal' => 3000.00,
                'total_amount' => 3000.00,
                'paid_amount' => 3000.00,
                'balance_amount' => 0.00,
                'due_date' => Carbon::now()->addDays(10),
                'status' => 'paid',
            ]);

            FeePayment::create([
                'tenant_id' => $this->tenant->id,
                'branch_id' => $this->branch->id,
                'student_id' => $student->id,
                'receipt_number' => 'REC-STX-' . (1000 + $i),
                'amount_paid' => 3000.00,
                'payment_date' => Carbon::now(),
                'payment_method' => 'cash',
                'status' => 'successful',
            ]);
        }
    }

    public function test_reporting_engine_paginates_students_report(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/reports/students?branch_id=' . $this->branch->id . '&per_page=10', [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'report_type' => 'students',
            ]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_reporting_engine_generates_csv_and_logs_audit_trail(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->get('/api/v1/reports/fees/export/csv?branch_id=' . $this->branch->id, [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringContainsString('Receipt Number', $content);
        $this->assertStringContainsString('REC-STX-1001', $content);

        // Verify Non-repudiation Audit Log
        $audit = AuditLog::where('action', 'exported')
            ->where('user_id', $this->adminUser->id)
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('fees', $audit->new_values['report_type']);
        $this->assertEquals('csv', $audit->new_values['format']);
    }

    public function test_reporting_engine_renders_printable_html(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->get('/api/v1/reports/attendance/export/print?branch_id=' . $this->branch->id, [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $html = $response->getContent();
        $this->assertStringContainsString('ATTENDANCE REPORT', $html);
        $this->assertStringContainsString('window.print()', $html);
    }

    public function test_async_export_dispatches_background_job_and_updates_status(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->adminUser);

        // Dispatch async export
        $response = $this->postJson('/api/v1/reports/students/export/async', [
            'branch_id' => $this->branch->id,
        ], [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
            ]);

        $jobId = $response->json('job_id');
        $this->assertNotNull($jobId);

        $exportJob = ExportJob::findOrFail($jobId);
        $this->assertContains($exportJob->status, ['pending', 'processing', 'completed']);
        $this->assertEquals('students', $exportJob->export_type);

        // Ensure job is processed and completed
        if ($exportJob->status !== 'completed') {
            $jobHandler = new ProcessExportJob($jobId, ['branch_id' => $this->branch->id]);
            $jobHandler->handle(app(ReportingEngineService::class));
            $exportJob->refresh();
        }

        $this->assertEquals('completed', $exportJob->status);
        $this->assertNotNull($exportJob->file_path);

        // Verify status check endpoint
        $statusCheck = $this->getJson('/api/v1/reports/export-jobs/' . $jobId, [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $statusCheck->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $jobId,
                    'status' => 'completed',
                ],
            ]);
    }

    public function test_invalid_report_type_returns_422_error(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/reports/non_existent_domain_report', [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }
}
