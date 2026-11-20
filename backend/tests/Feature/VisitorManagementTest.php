<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGatePass;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorPass;
use App\Models\VisitorStudentPickup;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisitorManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected User $adminUser;
    protected Employee $hostEmployee;
    protected User $hostUser;
    protected Student $student;
    protected User $studentUser;
    protected Guardian $guardian;
    protected User $guardianUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Bishop Cotton Boys School',
            'slug' => 'bishop-cotton-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@bishopcotton.edu',
            'contact_phone' => '+919876555555',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bishop Cotton Campus',
            'code' => 'BCB-' . rand(100, 999),
        ]);

        $this->branch = SchoolBranch::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Main Gate Security Wing',
            'branch_code' => 'GW-' . rand(100, 999),
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
            'code' => 'AY-VIS-' . rand(10, 99),
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        $this->class = SchoolClass::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Class 7',
            'code' => 'CLS-7-' . rand(100, 999),
            'order_index' => 7,
            'is_active' => true,
        ]);

        $this->section = Section::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'name' => 'Section C',
            'code' => '7-C-' . rand(100, 999),
            'capacity' => 35,
        ]);

        Role::firstOrCreate(['name' => 'school_admin'], ['guard_name' => 'web', 'display_name' => 'School Admin']);

        $this->adminUser = User::factory()->create([
            'first_name' => 'Security',
            'last_name' => 'Marshal',
            'email' => 'security.' . Str::random(8) . '@bishopcotton.edu',
            'phone' => '+9191' . rand(10000000, 99999999),
        ]);
        $this->adminUser->assignRole('school_admin', $this->tenant->id);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        // Host employee
        $dept = EmployeeDepartment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Academic Affairs',
            'code' => 'ACA-' . rand(100, 999),
        ]);

        $desig = EmployeeDesignation::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'department_id' => $dept->id,
            'title' => 'Vice Principal',
            'code' => 'VP-' . rand(100, 999),
        ]);

        $this->hostUser = User::factory()->create([
            'first_name' => 'Dennis',
            'last_name' => 'Dsilva',
            'email' => 'dennis.' . Str::random(8) . '@bishopcotton.edu',
            'phone' => '+9192' . rand(10000000, 99999999),
        ]);

        $this->hostEmployee = Employee::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->hostUser->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employee_code' => 'EMP-VIS-' . rand(1000, 9999),
            'first_name' => 'Dennis',
            'last_name' => 'Dsilva',
            'gender' => 'male',
            'date_of_birth' => '1975-02-14',
            'date_of_joining' => '2018-05-01',
            'email' => 'dennis.emp.' . Str::random(8) . '@bishopcotton.edu',
            'phone' => '+9193' . rand(10000000, 99999999),
            'status' => 'active',
        ]);

        // Student and Guardian
        $this->studentUser = User::factory()->create([
            'first_name' => 'Samuel',
            'last_name' => 'Dsilva',
            'email' => 'samuel.' . Str::random(8) . '@bishopcotton.edu',
            'phone' => '+9194' . rand(10000000, 99999999),
        ]);
        $this->student = Student::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->studentUser->id,
            'admission_number' => 'ADM-VIS-' . rand(1000, 9999),
            'first_name' => 'Samuel',
            'last_name' => 'Dsilva',
            'date_of_birth' => '2013-07-22',
            'gender' => 'male',
            'admission_date' => '2026-04-01',
            'status' => 'active',
        ]);

        StudentEnrollment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_number' => '05',
            'status' => 'enrolled',
        ]);

        $this->guardianUser = User::factory()->create([
            'first_name' => 'George',
            'last_name' => 'Dsilva',
            'email' => 'george.' . Str::random(8) . '@bishopcotton.edu',
            'phone' => '+9195' . rand(10000000, 99999999),
        ]);
        $this->guardian = Guardian::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->guardianUser->id,
            'first_name' => 'George',
            'last_name' => 'Dsilva',
            'relation_type' => 'father',
            'phone' => '+919500112233',
            'email' => 'george.dsilva@example.com',
            'is_primary' => true,
        ]);
    }

    public function test_can_register_visitor_profile(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->postJson('/api/v1/operations/visitors', [
            'branch_id' => $this->branch->id,
            'full_name' => 'Robert Fernandes',
            'phone' => '+919876598765',
            'national_id' => 'AADHAAR-9090-8080-7070',
            'photo_url' => 'https://storage.school.edu/visitors/photo1.jpg',
            'address' => '12 Richmond Road, Bangalore',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.full_name', 'Robert Fernandes')
            ->assertJsonPath('data.phone', '+919876598765');

        $this->assertDatabaseHas('visitors', [
            'full_name' => 'Robert Fernandes',
            'phone' => '+919876598765',
        ]);
    }

    public function test_can_check_in_visitor_and_issue_pass(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->postJson('/api/v1/operations/visitors/check-in', [
            'branch_id' => $this->branch->id,
            'full_name' => 'Anita Desai',
            'phone' => '+919811223344',
            'national_id' => 'PAN-ABCD1234E',
            'host_employee_id' => $this->hostEmployee->id,
            'purpose' => 'Parent Teacher Consultation',
            'badge_printed' => true,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'checked_in')
            ->assertJsonPath('data.purpose', 'Parent Teacher Consultation');

        $this->assertDatabaseHas('visitor_passes', [
            'host_employee_id' => $this->hostEmployee->id,
            'purpose' => 'Parent Teacher Consultation',
            'status' => 'checked_in',
        ]);

        $passNumber = $res->json('data.pass_number');
        $this->assertStringStartsWith('VP-', $passNumber);
    }

    public function test_can_check_in_visitor_with_authorized_student_pickup(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->postJson('/api/v1/operations/visitors/check-in', [
            'branch_id' => $this->branch->id,
            'full_name' => 'George Dsilva',
            'phone' => '+919500112233',
            'host_employee_id' => $this->hostEmployee->id,
            'purpose' => 'Early Child Pickup - Doctor Appointment',
            'student_pickup' => [
                'student_id' => $this->student->id,
                'guardian_id' => $this->guardian->id,
                'authorization_status' => 'verified',
                'relationship_to_student' => 'Father',
                'parent_consent_confirmed' => true,
                'parent_contact_phone' => '+919500112233',
                'verification_notes' => 'Identity verified with Government Photo ID card.',
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pickup.authorization_status', 'verified')
            ->assertJsonPath('data.pickup.relationship_to_student', 'Father');

        $this->assertDatabaseHas('visitor_student_pickups', [
            'student_id' => $this->student->id,
            'guardian_id' => $this->guardian->id,
            'authorization_status' => 'verified',
        ]);
    }

    public function test_can_check_out_visitor(): void
    {
        Sanctum::actingAs($this->adminUser);

        $checkInRes = $this->postJson('/api/v1/operations/visitors/check-in', [
            'branch_id' => $this->branch->id,
            'full_name' => 'Michael Scott',
            'phone' => '+919844556677',
            'host_employee_id' => $this->hostEmployee->id,
            'purpose' => 'Textbook Delivery inspection',
        ]);

        $passId = $checkInRes->json('data.id');

        $outRes = $this->postJson("/api/v1/operations/visitors/passes/{$passId}/check-out");

        $outRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'checked_out');

        $this->assertDatabaseHas('visitor_passes', [
            'id' => $passId,
            'status' => 'checked_out',
        ]);
        $this->assertNotNull($outRes->json('data.check_out_time'));
    }

    public function test_cannot_checkout_already_checked_out_pass(): void
    {
        Sanctum::actingAs($this->adminUser);

        $checkInRes = $this->postJson('/api/v1/operations/visitors/check-in', [
            'branch_id' => $this->branch->id,
            'full_name' => 'Jim Halpert',
            'phone' => '+919866778899',
            'host_employee_id' => $this->hostEmployee->id,
            'purpose' => 'Campus Inspection',
        ]);

        $passId = $checkInRes->json('data.id');

        // Check out once
        $this->postJson("/api/v1/operations/visitors/passes/{$passId}/check-out")->assertStatus(200);

        // Try checking out again
        $secondOutRes = $this->postJson("/api/v1/operations/visitors/passes/{$passId}/check-out");
        $secondOutRes->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('already checked out', $secondOutRes->json('message'));
    }

    public function test_can_retrieve_visitor_visit_history(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Same visitor phone checking in twice on different purposes
        $phone = '+919855443322';

        $res1 = $this->postJson('/api/v1/operations/visitors/check-in', [
            'branch_id' => $this->branch->id,
            'full_name' => 'Sarah Connor',
            'phone' => $phone,
            'host_employee_id' => $this->hostEmployee->id,
            'purpose' => 'PTM Term 1',
        ]);

        $visitorId = $res1->json('data.visitor_id');

        // Second visit
        $this->postJson('/api/v1/operations/visitors/check-in', [
            'branch_id' => $this->branch->id,
            'full_name' => 'Sarah Connor',
            'phone' => $phone,
            'host_employee_id' => $this->hostEmployee->id,
            'purpose' => 'Annual Day Rehearsal Guest',
        ]);

        $histRes = $this->getJson("/api/v1/operations/visitors/{$visitorId}/history");

        $histRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_visits', 2)
            ->assertJsonCount(2, 'data.passes');
    }

    public function test_can_issue_and_exit_student_gate_pass(): void
    {
        Sanctum::actingAs($this->adminUser);

        // 1. Issue gate pass
        $gateRes = $this->postJson('/api/v1/operations/visitors/gate-passes', [
            'branch_id' => $this->branch->id,
            'student_id' => $this->student->id,
            'guardian_id' => $this->guardian->id,
            'reason' => 'Emergency family requirement',
        ]);

        $gateRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $gatePassId = $gateRes->json('data.id');
        $this->assertStringStartsWith('GP-', $gateRes->json('data.pass_number'));

        // 2. Mark exit at gate
        $exitRes = $this->postJson("/api/v1/operations/visitors/gate-passes/{$gatePassId}/exit");

        $exitRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'exited');

        $this->assertDatabaseHas('student_gate_passes', [
            'id' => $gatePassId,
            'status' => 'exited',
        ]);
        $this->assertNotNull($exitRes->json('data.actual_exit_time'));
    }

    public function test_visitor_front_desk_reports(): void
    {
        Sanctum::actingAs($this->adminUser);

        $this->postJson('/api/v1/operations/visitors/check-in', [
            'branch_id' => $this->branch->id,
            'full_name' => 'Front Desk Test Guest',
            'phone' => '+919900887766',
            'host_employee_id' => $this->hostEmployee->id,
            'purpose' => 'Official Inquiry',
        ]);

        $repRes = $this->getJson("/api/v1/operations/visitors/reports?branch_id={$this->branch->id}");

        $repRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_visitors_today', 1)
            ->assertJsonPath('data.currently_inside_campus', 1);
    }

    public function test_tenant_isolation_prevents_cross_tenant_visitor_access(): void
    {
        Sanctum::actingAs($this->adminUser);

        $checkInRes = $this->postJson('/api/v1/operations/visitors/check-in', [
            'branch_id' => $this->branch->id,
            'full_name' => 'Secret Academy Visitor',
            'phone' => '+919988998899',
            'host_employee_id' => $this->hostEmployee->id,
            'purpose' => 'Secret Meeting',
        ]);
        $passId = $checkInRes->json('data.id');

        // Second tenant
        $tenant2 = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'The Doon International',
            'slug' => 'doon-intl-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@doonintl.edu',
            'contact_phone' => '+919876566666',
            'status' => 'active',
        ]);

        $user2 = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'FrontDeskAdmin',
            'email' => 'other.vis.' . Str::random(8) . '@doonintl.edu',
            'phone' => '+9190' . rand(10000000, 99999999),
        ]);
        $user2->assignRole('school_admin', $tenant2->id);
        TenantMembership::create([
            'tenant_id' => $tenant2->id,
            'user_id' => $user2->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        app(TenantContext::class)->setTenant($tenant2);
        Sanctum::actingAs($user2);

        $listRes = $this->getJson('/api/v1/operations/visitors/passes');
        $purposes = collect($listRes->json('data'))->pluck('purpose');
        $this->assertFalse($purposes->contains('Secret Meeting'));

        $outRes = $this->postJson("/api/v1/operations/visitors/passes/{$passId}/check-out");
        $outRes->assertStatus(404);
    }
}

