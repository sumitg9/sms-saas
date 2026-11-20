<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use App\Models\Role;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected SchoolClass $class;
    protected Section $section;
    protected User $adminUser;
    protected User $platformUser;
    protected User $accountantUser;
    protected User $teacherUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant & Branch
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Delhi World Public School',
            'slug' => 'dwps-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@dwps.edu',
            'contact_phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'DWPS Main Campus',
            'code' => 'DWPS-' . rand(100, 999),
        ]);

        $this->branch = SchoolBranch::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Senior Wing',
            'branch_code' => 'SW-' . rand(100, 999),
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
            'name' => 'Grade 10',
            'code' => 'G10',
            'display_order' => 10,
        ]);

        $this->section = Section::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'name' => 'Section A',
            'capacity' => 40,
        ]);

        // Users & Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'], ['display_name' => 'Super Admin']);
        $schoolAdminRole = Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'web'], ['display_name' => 'School Admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'web'], ['display_name' => 'Accountant']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web'], ['display_name' => 'Teacher']);

        // Platform Super Admin
        $this->platformUser = User::create([
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'email' => 'platform-' . Str::random(6) . '@saas.com',
            'password' => bcrypt('password123'),
            'is_platform_admin' => true,
            'status' => 'active',
        ]);
        $this->platformUser->assignRole($superAdminRole);

        // School Admin
        $this->adminUser = User::create([
            'first_name' => 'Principal',
            'last_name' => 'Sharma',
            'email' => 'principal-' . Str::random(6) . '@dwps.edu',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->adminUser->assignRole($schoolAdminRole);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
            'branch_id' => $this->branch->id,
            'role' => 'school_admin',
            'is_active' => true,
        ]);

        // Accountant
        $this->accountantUser = User::create([
            'first_name' => 'Bursar',
            'last_name' => 'Gupta',
            'email' => 'accountant-' . Str::random(6) . '@dwps.edu',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->accountantUser->assignRole($accountantRole);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->accountantUser->id,
            'branch_id' => $this->branch->id,
            'role' => 'accountant',
            'is_active' => true,
        ]);

        // Teacher
        $this->teacherUser = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'Verma',
            'email' => 'teacher-' . Str::random(6) . '@dwps.edu',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->teacherUser->assignRole($teacherRole);
        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->teacherUser->id,
            'branch_id' => $this->branch->id,
            'role' => 'teacher',
            'is_active' => true,
        ]);

        // Seed some students, employee, and fee records
        $this->seedSampleData();
    }

    private function seedSampleData(): void
    {
        // 2 Students
        for ($i = 1; $i <= 2; $i++) {
            $student = Student::create([
                'tenant_id' => $this->tenant->id,
                'school_id' => $this->school->id,
                'branch_id' => $this->branch->id,
                'admission_number' => 'ADM-' . rand(10000, 99999),
                'first_name' => 'Student' . $i,
                'last_name' => 'Test',
                'admission_date' => '2026-04-01',
                'date_of_birth' => '2012-05-15',
                'gender' => 'male',
                'status' => 'active',
            ]);

            StudentEnrollment::create([
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
        }

        // 1 Teacher Employee
        $dept = EmployeeDepartment::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Science Department',
            'code' => 'SCI-' . rand(100, 999),
        ]);

        $desig = EmployeeDesignation::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'department_id' => $dept->id,
            'title' => 'Senior Physics Teacher',
            'code' => 'SPT-' . rand(100, 999),
        ]);

        Employee::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->teacherUser->id,
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employee_code' => 'EMP-' . rand(1000, 9999),
            'first_name' => 'Teacher',
            'last_name' => 'Verma',
            'email' => $this->teacherUser->email,
            'phone' => '+91' . rand(7000000000, 7999999999),
            'gender' => 'female',
            'date_of_birth' => '1990-08-12',
            'date_of_joining' => '2023-06-01',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
        ]);

        // Fee Category & Structure
        $feeCat = FeeCategory::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Tuition Fee Q1',
            'code' => 'TF-Q1',
        ]);

        $feeStructure = FeeStructure::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'fee_category_id' => $feeCat->id,
            'name' => 'Grade 10 Tuition Fee Q1',
            'amount' => 5000.00,
            'due_date' => Carbon::now()->addDays(15),
        ]);

        $student = Student::first();
        $enrollment = StudentEnrollment::where('student_id', $student->id)->first();
        FeeInvoice::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'academic_session_id' => $this->session->id,
            'invoice_number' => 'INV-' . rand(10000, 99999),
            'subtotal' => 5000.00,
            'total_amount' => 5000.00,
            'paid_amount' => 2500.00,
            'balance_amount' => 2500.00,
            'due_date' => Carbon::now()->addDays(15),
            'status' => 'partially_paid',
        ]);

        FeePayment::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'receipt_number' => 'REC-' . rand(10000, 99999),
            'amount_paid' => 2500.00,
            'payment_date' => Carbon::now(),
            'payment_method' => 'cash',
            'status' => 'successful',
        ]);

        $expCat = ExpenseCategory::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Campus Utilities',
            'code' => 'UTIL-' . rand(100, 999),
        ]);

        Expense::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'expense_category_id' => $expCat->id,
            'voucher_number' => 'VOUCH-' . rand(1000, 9999),
            'title' => 'Electricity & Water Bill',
            'amount' => 1200.00,
            'expense_date' => Carbon::today(),
            'payment_method' => 'bank_transfer',
        ]);
    }

    public function test_platform_super_admin_analytics_distinguishes_actual_revenue_from_projections(): void
    {
        // Create a subscription plan and payment
        $plan = SubscriptionPlan::create([
            'name' => 'Enterprise School Tier',
            'slug' => 'enterprise-' . Str::random(5),
            'price' => 12000.00,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => Carbon::now()->startOfMonth(),
            'current_period_end' => Carbon::now()->endOfMonth(),
        ]);

        $subInvoice = SubscriptionInvoice::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'invoice_number' => 'SUB-INV-' . rand(1000, 9999),
            'subtotal' => 12000.00,
            'tax_amount' => 0.00,
            'total_amount' => 12000.00,
            'currency' => 'INR',
            'status' => 'paid',
            'due_date' => Carbon::now(),
            'paid_at' => Carbon::now(),
        ]);

        // Succeeded payment (Actual settled cash)
        SubscriptionPayment::create([
            'invoice_id' => $subInvoice->id,
            'subscription_id' => $subscription->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 12000.00,
            'currency' => 'INR',
            'gateway' => 'razorpay',
            'transaction_reference' => 'TXN-' . rand(100000, 999999),
            'status' => 'succeeded',
            'payment_method' => 'card',
            'paid_at' => Carbon::now(),
        ]);

        Sanctum::actingAs($this->platformUser);

        $response = $this->getJson('/api/v1/analytics/platform');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $financialData = $response->json('data.financial_data');
        $this->assertNotNull($financialData);

        // Verification of actual vs projected distinction
        $actual = $financialData['actual'];
        $projections = $financialData['projections'];

        $this->assertFalse($actual['is_estimate'], 'Actual revenue must have is_estimate: false');
        $this->assertTrue($projections['is_estimate'], 'Projections must have is_estimate: true');
        $this->assertGreaterThanOrEqual(12000.00, $actual['total_collected_all_time']);
        $this->assertArrayHasKey('monthly_recurring_revenue_mrr', $projections);
        $this->assertArrayHasKey('annual_recurring_revenue_arr', $projections);
    }

    public function test_non_platform_admin_is_forbidden_from_platform_analytics(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/analytics/platform');

        $response->assertStatus(403);
    }

    public function test_school_analytics_returns_academic_and_operational_metrics(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/analytics/school?branch_id=' . $this->branch->id, [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');

        $this->assertEquals(2, $data['overview']['total_students']);
        $this->assertEquals(1, $data['overview']['total_teachers']);
        $this->assertArrayHasKey('finance', $data);
        $this->assertEquals(2500.00, $data['finance']['monthly_fee_collections']);
        $this->assertEquals(2500.00, $data['finance']['outstanding_fees']);
        $this->assertArrayHasKey('class_distribution', $data);
        $this->assertArrayHasKey('admissions', $data);
        $this->assertArrayHasKey('attendance', $data);
    }

    public function test_financial_analytics_returns_accountant_overview(): void
    {
        Sanctum::actingAs($this->accountantUser);

        $response = $this->getJson('/api/v1/analytics/financial?branch_id=' . $this->branch->id, [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');

        $this->assertArrayHasKey('collections', $data);
        $this->assertEquals(2500.00, $data['collections']['this_month']);
        $this->assertEquals(2500.00, $data['collections']['total_outstanding_arrears']);
        $this->assertArrayHasKey('expenses', $data);
        $this->assertArrayHasKey('payment_modes', $data);
        $this->assertEquals(1200.00, $data['expenses']['this_month']);
    }

    public function test_role_routed_dashboard_endpoint(): void
    {
        // 1. School Admin
        Sanctum::actingAs($this->adminUser);
        $resAdmin = $this->getJson('/api/v1/analytics/dashboard?branch_id=' . $this->branch->id, [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);
        $resAdmin->assertStatus(200)
            ->assertJson(['success' => true, 'role' => 'school_admin']);

        // 2. Accountant
        Sanctum::actingAs($this->accountantUser);
        $resAcct = $this->getJson('/api/v1/analytics/dashboard?branch_id=' . $this->branch->id, [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);
        $resAcct->assertStatus(200)
            ->assertJson(['success' => true, 'role' => 'accountant']);

        // 3. Teacher
        Sanctum::actingAs($this->teacherUser);
        $resTeacher = $this->getJson('/api/v1/analytics/dashboard?branch_id=' . $this->branch->id, [
            'X-Tenant-ID' => $this->tenant->id,
            'X-Branch-ID' => $this->branch->id,
        ]);
        $resTeacher->assertStatus(200)
            ->assertJson(['success' => true, 'role' => 'teacher']);
    }
}
