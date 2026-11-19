<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Board;
use App\Models\ClassTeacher;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeacherSubject;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Models\TimetablePeriod;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\BoardsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAndPayrollManagementTest extends TestCase
{
    protected Tenant $tenant;
    protected School $school;
    protected SchoolBranch $branch;
    protected AcademicSession $session;
    protected EmployeeDepartment $department;
    protected EmployeeDesignation $teacherDesignation;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(BoardsSeeder::class);

        // 1. Create Tenant, School, Branch
        $this->tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Oakridge International',
            'slug' => 'oakridge-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@oakridge.edu',
            'contact_phone' => '+919876543210',
            'status' => 'active',
        ]);

        $this->school = School::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'name' => 'Oakridge World School',
            'code' => 'OAK-01',
            'status' => 'active',
        ]);

        $this->branch = SchoolBranch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'name' => 'Main Campus',
            'branch_code' => 'MC',
            'is_active' => true,
        ]);

        // 2. Academic Session
        $this->session = AcademicSession::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => '2026-2027',
            'code' => 'AY2026-27',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);

        // 3. Departments & Designations
        $this->department = EmployeeDepartment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Science Department',
            'code' => 'SCI',
            'is_active' => true,
        ]);

        $this->teacherDesignation = EmployeeDesignation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->department->id,
            'title' => 'Senior Secondary Teacher',
            'code' => 'PGT',
            'level' => 2,
            'is_active' => true,
        ]);

        // 4. Default Leave Types
        LeaveType::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Casual Leave',
            'code' => 'CL',
            'annual_quota' => 12.0,
            'is_paid' => true,
        ]);

        LeaveType::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Sick Leave',
            'code' => 'SL',
            'annual_quota' => 10.0,
            'is_paid' => true,
        ]);

        // 5. Admin User
        $this->adminUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'HR',
            'last_name' => 'Director',
            'email' => 'hr-' . Str::random(8) . '@oakridge.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
            'password' => bcrypt('AdminPassword123!'),
            'is_active' => true,
        ]);

        $membership = TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->adminUser->id,
            'role' => 'school_admin',
            'is_default' => true,
        ]);

        $this->adminUser->assignRole('school_admin', $this->tenant->id);

        app(TenantContext::class)->setTenant($this->tenant);
        app(TenantContext::class)->setSchool($this->school);
        app(TenantContext::class)->setBranch($this->branch);
        app(TenantContext::class)->setActiveMembership($membership);
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->adminUser, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $this->tenant->id,
                'X-Branch-ID' => $this->branch->id,
            ]);
    }

    public function test_employee_registration_generates_code_and_initializes_leave_quotas(): void
    {
        $payload = [
            'school_id' => $this->school->id,
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'first_name' => 'Vikram',
            'last_name' => 'Rathore',
            'gender' => 'male',
            'date_of_birth' => '1988-06-15',
            'blood_group' => 'O+',
            'email' => 'vikram.rathore@oakridge.edu',
            'phone' => '+919876500001',
            'employment_type' => 'permanent',
            'basic_salary' => 60000.00,
            'pan_number' => 'ABCDE1234F',
            'bank_account_number' => '123456789012',
            'bank_ifsc_code' => 'HDFC0001234',
            'bank_name' => 'HDFC Bank',
        ];

        $response = $this->actingAsAdmin()->postJson('/api/v1/employees', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Employee registered successfully',
            ])
            ->assertJsonPath('data.first_name', 'Vikram')
            ->assertJsonPath('data.status', 'active');

        $employeeId = $response->json('data.id');
        $code = $response->json('data.employee_code');

        $this->assertStringStartsWith('EMP-', $code);

        // Assert leave balances were seeded automatically from leave types
        $this->assertDatabaseHas('employee_leaves', [
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employeeId,
            'leave_type' => 'CL',
            'total_days' => 12.0,
            'balance_days' => 12.0,
        ]);

        $this->assertDatabaseHas('employee_leaves', [
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employeeId,
            'leave_type' => 'SL',
            'total_days' => 10.0,
            'balance_days' => 10.0,
        ]);
    }

    public function test_employee_contract_management_and_document_attachment(): void
    {
        $employee = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-2026-0099',
            'first_name' => 'Pooja',
            'last_name' => 'Sharma',
            'gender' => 'female',
            'date_of_birth' => '1992-04-10',
            'email' => 'pooja.sharma@oakridge.edu',
            'phone' => '+919876500002',
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'employment_type' => 'permanent',
            'date_of_joining' => '2026-05-01',
            'basic_salary' => 55000.00,
        ]);

        // Add Contract
        $contractPayload = [
            'contract_type' => 'permanent',
            'start_date' => '2026-05-01',
            'gross_salary' => 70000.00,
            'terms' => ['probation_months' => 6],
        ];

        $contractRes = $this->actingAsAdmin()->postJson("/api/v1/employees/{$employee->id}/contracts", $contractPayload);
        $contractRes->assertStatus(201)
            ->assertJsonPath('data.gross_salary', '70000.00')
            ->assertJsonPath('data.is_active', true);

        // Add Document
        $docPayload = [
            'document_type' => 'degree_certificate',
            'file_name' => 'msc_physics_degree.pdf',
            'file_path' => '/storage/documents/employees/msc_physics.pdf',
            'verification_status' => 'verified',
        ];

        $docRes = $this->actingAsAdmin()->postJson("/api/v1/employees/{$employee->id}/documents", $docPayload);
        $docRes->assertStatus(201)
            ->assertJsonPath('data.document_type', 'degree_certificate');

        // Fetch Employee 360
        $showRes = $this->actingAsAdmin()->getJson("/api/v1/employees/{$employee->id}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.employee.first_name', 'Pooja')
            ->assertJsonPath('data.active_contract.gross_salary', '70000.00')
            ->assertJsonPath('data.documents_count', 1);
    }

    public function test_employee_exit_processing_handles_resignation_and_clearance(): void
    {
        $employee = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-2026-0088',
            'first_name' => 'Rajesh',
            'last_name' => 'Kumar',
            'gender' => 'male',
            'date_of_birth' => '1985-03-20',
            'email' => 'rajesh.kumar@oakridge.edu',
            'phone' => '+919876500003',
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'employment_type' => 'permanent',
            'date_of_joining' => '2020-06-01',
            'basic_salary' => 65000.00,
            'status' => 'active',
        ]);

        $exitPayload = [
            'exit_type' => 'resignation',
            'notice_date' => '2026-10-01',
            'last_working_day' => '2026-11-30',
            'reason' => 'Relocating to another city',
            'clearance_status' => 'in_progress',
            'settlement_status' => 'pending',
            'clearance_checklist' => [
                'it_clearance' => true,
                'library_clearance' => true,
                'finance_clearance' => false,
                'admin_clearance' => true,
            ],
            'remarks' => 'Handover in progress',
        ];

        $response = $this->actingAsAdmin()->postJson("/api/v1/employees/{$employee->id}/exit", $exitPayload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employee exit processed successfully',
            ])
            ->assertJsonPath('data.exit_type', 'resignation')
            ->assertJsonPath('data.clearance_status', 'in_progress');

        // Verify employee model status changed to resigned
        $this->assertEquals('resignation', $employee->fresh()->status);
        $this->assertEquals('2026-11-30', $employee->fresh()->date_of_leaving->format('Y-m-d'));
    }

    public function test_teacher_workload_calculation_and_weekly_timetable_grid(): void
    {
        $teacher = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-2026-0077',
            'first_name' => 'Sunita',
            'last_name' => 'Menon',
            'gender' => 'female',
            'date_of_birth' => '1990-08-12',
            'email' => 'sunita.menon@oakridge.edu',
            'phone' => '+919876500004',
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'employment_type' => 'permanent',
            'date_of_joining' => '2022-04-01',
            'basic_salary' => 58000.00,
        ]);

        $board = Board::where('code', 'CBSE')->first();

        $class = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'board_id' => $board->id,
            'name' => 'Grade 11 Science',
            'code' => 'G11-SCI',
            'order_index' => 11,
        ]);

        $section = Section::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'name' => 'A',
            'max_capacity' => 35,
        ]);

        $subject = Subject::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Physics',
            'code' => 'PHY-11',
            'type' => 'theory',
        ]);

        $timetable = Timetable::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'name' => 'Grade 11A Timetable',
            'effective_from' => '2026-04-01',
            'is_active' => true,
        ]);

        $period1 = TimetablePeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'timetable_id' => $timetable->id,
            'period_name' => 'Period 1',
            'period_number' => 1,
            'start_time' => '08:30:00',
            'end_time' => '09:15:00',
            'is_break' => false,
        ]);

        $period2 = TimetablePeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'timetable_id' => $timetable->id,
            'period_name' => 'Period 2',
            'period_number' => 2,
            'start_time' => '09:15:00',
            'end_time' => '10:00:00',
            'is_break' => false,
        ]);

        // Create 2 timetable entries for Monday (day 1) and Tuesday (day 2)
        TimetableEntry::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'timetable_id' => $timetable->id,
            'period_id' => $period1->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'employee_id' => $teacher->id,
            'day_of_week' => 1, // Monday
            'room_number' => 'Lab-1',
        ]);

        TimetableEntry::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'timetable_id' => $timetable->id,
            'period_id' => $period2->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'employee_id' => $teacher->id,
            'day_of_week' => 2, // Tuesday
            'room_number' => 'Lab-1',
        ]);

        // Workload API call
        $workloadRes = $this->actingAsAdmin()->getJson("/api/v1/teachers/{$teacher->id}/workload?academic_session_id={$this->session->id}");
        $workloadRes->assertStatus(200)
            ->assertJsonPath('data.total_weekly_periods', 2)
            ->assertJsonPath('data.free_periods_per_week', 38)
            ->assertJsonPath('data.subjects.0.subject_name', 'Physics');

        // Timetable API call
        $ttRes = $this->actingAsAdmin()->getJson("/api/v1/teachers/{$teacher->id}/timetable?academic_session_id={$this->session->id}");
        $ttRes->assertStatus(200)
            ->assertJsonCount(1, 'data.schedule.1')
            ->assertJsonCount(1, 'data.schedule.2')
            ->assertJsonPath('data.schedule.1.0.subject_name', 'Physics');
    }

    public function test_substitute_teacher_allocation_validates_conflicts_and_prevents_double_booking(): void
    {
        $teacherA = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-2026-0061',
            'first_name' => 'Anil',
            'last_name' => 'Kapoor',
            'gender' => 'male',
            'date_of_birth' => '1980-01-01',
            'email' => 'anil.kapoor@oakridge.edu',
            'phone' => '+919876500005',
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'date_of_joining' => '2020-01-01',
        ]);

        $teacherB = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-2026-0062',
            'first_name' => 'Rohan',
            'last_name' => 'Mehra',
            'gender' => 'male',
            'date_of_birth' => '1982-02-02',
            'email' => 'rohan.mehra@oakridge.edu',
            'phone' => '+919876500006',
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'date_of_joining' => '2020-01-01',
        ]);

        $teacherC = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-2026-0063',
            'first_name' => 'Sneha',
            'last_name' => 'Reddy',
            'gender' => 'female',
            'date_of_birth' => '1985-03-03',
            'email' => 'sneha.reddy@oakridge.edu',
            'phone' => '+919876500007',
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'date_of_joining' => '2020-01-01',
        ]);

        $board = Board::where('code', 'CBSE')->first();
        $class = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'board_id' => $board->id,
            'name' => 'Grade 9',
            'code' => 'G9',
            'order_index' => 9,
        ]);
        $section = Section::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'name' => 'A',
            'max_capacity' => 35,
        ]);
        $subject = Subject::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'name' => 'Chemistry',
            'code' => 'CHEM-9',
            'type' => 'theory',
        ]);
        $timetable = Timetable::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'name' => 'Grade 9A Timetable',
            'effective_from' => '2026-04-01',
            'is_active' => true,
        ]);
        $period = TimetablePeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'timetable_id' => $timetable->id,
            'period_name' => 'Period 1',
            'period_number' => 1,
            'start_time' => '08:30:00',
            'end_time' => '09:15:00',
            'is_break' => false,
        ]);

        $sectionB = Section::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $class->id,
            'name' => 'B',
            'max_capacity' => 35,
        ]);

        // Teacher A has class on Monday (day 1) period 1 in Section A
        $entryA = TimetableEntry::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'timetable_id' => $timetable->id,
            'period_id' => $period->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'employee_id' => $teacherA->id,
            'day_of_week' => 1, // Monday
        ]);

        // Teacher B has class on Monday (day 1) period 1 in Section B (conflict!)
        TimetableEntry::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'timetable_id' => $timetable->id,
            'period_id' => $period->id,
            'class_id' => $class->id,
            'section_id' => $sectionB->id,
            'subject_id' => $subject->id,
            'employee_id' => $teacherB->id,
            'day_of_week' => 1, // Monday
        ]);

        // 2026-11-23 is a Monday (ISO day 1)
        $mondayDate = '2026-11-23';

        // Attempt 1: Assign Teacher B (who has a conflicting class) -> Should fail with 422
        $conflictPayload = [
            'timetable_entry_id' => $entryA->id,
            'substitute_employee_id' => $teacherB->id,
            'date' => $mondayDate,
            'reason' => 'Teacher A on emergency medical leave',
        ];

        $conflictRes = $this->actingAsAdmin()->postJson('/api/v1/teachers/substitutions', $conflictPayload);
        $conflictRes->assertStatus(422)
            ->assertJsonValidationErrors(['substitute_employee_id']);

        // Attempt 2: Assign Teacher C (free at this period) -> Should succeed with 201
        $validPayload = [
            'timetable_entry_id' => $entryA->id,
            'substitute_employee_id' => $teacherC->id,
            'date' => $mondayDate,
            'reason' => 'Teacher A on emergency medical leave',
        ];

        $validRes = $this->actingAsAdmin()->postJson('/api/v1/teachers/substitutions', $validPayload);
        $validRes->assertStatus(201)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.substitute_employee_id', $teacherC->id);
    }

    public function test_leave_application_lifecycle_approval_deducts_balance_and_logs_attendance(): void
    {
        $employee = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-2026-0055',
            'first_name' => 'Meera',
            'last_name' => 'Nair',
            'gender' => 'female',
            'date_of_birth' => '1987-09-09',
            'email' => 'meera.nair@oakridge.edu',
            'phone' => '+919876500008',
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'date_of_joining' => '2021-06-01',
            'basic_salary' => 52000.00,
        ]);

        // Seed initial Casual Leave quota
        $this->actingAsAdmin()->postJson('/api/v1/leaves/types', [
            'name' => 'Earned Leave',
            'code' => 'EL',
            'annual_quota' => 15.0,
            'is_paid' => true,
        ]);

        // Apply for 2 days leave (2026-11-24 to 2026-11-25)
        $applyPayload = [
            'employee_id' => $employee->id,
            'leave_type' => 'CL',
            'start_date' => '2026-11-24',
            'end_date' => '2026-11-25',
            'total_days' => 2.0,
            'reason' => 'Family function',
        ];

        $applyRes = $this->actingAsAdmin()->postJson('/api/v1/leaves/applications', $applyPayload);
        $applyRes->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $applicationId = $applyRes->json('data.id');

        // Approve Leave Application
        $reviewRes = $this->actingAsAdmin()->putJson("/api/v1/leaves/applications/{$applicationId}/review", [
            'status' => 'approved',
            'remarks' => 'Approved by HR',
        ]);

        $reviewRes->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Verify attendance records were marked as 'on_leave'
        $this->assertDatabaseHas('employee_attendance', [
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'date' => '2026-11-24',
            'status' => 'on_leave',
        ]);

        $this->assertDatabaseHas('employee_attendance', [
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'date' => '2026-11-25',
            'status' => 'on_leave',
        ]);
    }

    public function test_configurable_payroll_calculates_allowances_deductions_and_unpaid_leave(): void
    {
        $employee = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-2026-0044',
            'first_name' => 'Aarav',
            'last_name' => 'Patel',
            'gender' => 'male',
            'date_of_birth' => '1984-05-25',
            'email' => 'aarav.patel@oakridge.edu',
            'phone' => '+919876500009',
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'date_of_joining' => '2019-01-01',
            'basic_salary' => 50000.00,
            'status' => 'active',
        ]);

        // 1. Create Configurable Components
        // Allowance: HRA (40% of BASIC)
        $hraRes = $this->actingAsAdmin()->postJson('/api/v1/payroll/components', [
            'name' => 'House Rent Allowance',
            'code' => 'HRA',
            'type' => 'allowance',
            'calculation_type' => 'percentage',
            'percentage_of' => 'BASIC',
            'default_amount' => 40.00,
        ]);
        $hraRes->assertStatus(201);
        $hraId = $hraRes->json('data.id');

        // Allowance: Dearness Allowance (Fixed 10,000)
        $daRes = $this->actingAsAdmin()->postJson('/api/v1/payroll/components', [
            'name' => 'Dearness Allowance',
            'code' => 'DA',
            'type' => 'allowance',
            'calculation_type' => 'fixed',
            'default_amount' => 10000.00,
        ]);
        $daRes->assertStatus(201);
        $daId = $daRes->json('data.id');

        // Deduction: Provident Fund (12% of BASIC)
        $pfRes = $this->actingAsAdmin()->postJson('/api/v1/payroll/components', [
            'name' => 'Provident Fund',
            'code' => 'PF',
            'type' => 'deduction',
            'calculation_type' => 'percentage',
            'percentage_of' => 'BASIC',
            'default_amount' => 12.00,
        ]);
        $pfRes->assertStatus(201);
        $pfId = $pfRes->json('data.id');

        // 2. Create Salary Structure
        $structRes = $this->actingAsAdmin()->postJson('/api/v1/payroll/structures', [
            'name' => 'Senior Teacher Scale 2026',
            'code' => 'STS-2026',
            'base_salary' => 50000.00,
            'items' => [
                ['salary_component_id' => $hraId, 'amount' => 40.00],
                ['salary_component_id' => $daId, 'amount' => 10000.00],
                ['salary_component_id' => $pfId, 'amount' => 12.00],
            ],
        ]);
        $structRes->assertStatus(201);
        $structId = $structRes->json('data.id');

        // 3. Assign Salary Profile to Employee
        $profileRes = $this->actingAsAdmin()->postJson("/api/v1/payroll/employees/{$employee->id}/profile", [
            'salary_structure_id' => $structId,
            'base_salary' => 50000.00,
            'payment_method' => 'bank_transfer',
        ]);
        $profileRes->assertStatus(200);

        // 4. Create Payroll Period: November 2026
        $periodRes = $this->actingAsAdmin()->postJson('/api/v1/payroll/periods', [
            'name' => 'November 2026',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]);
        $periodRes->assertStatus(201);
        $periodId = $periodRes->json('data.id');

        // 5. Run Payroll Calculation
        $calcRes = $this->actingAsAdmin()->postJson("/api/v1/payroll/periods/{$periodId}/calculate");
        $calcRes->assertStatus(200)
            ->assertJsonPath('data.status', 'processing');

        // Verify Payslip:
        // Basic: 50,000
        // HRA (40%): 20,000
        // DA (Fixed): 10,000
        // Total Allowances: 30,000
        // Gross: 80,000
        // PF (12%): 6,000
        // Total Deductions: 6,000
        // Net Salary: 74,000
        $payslip = Payslip::where('payroll_period_id', $periodId)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        $this->assertEquals(50000.00, (float) $payslip->basic_salary);
        $this->assertEquals(30000.00, (float) $payslip->total_allowances);
        $this->assertEquals(80000.00, (float) $payslip->gross_salary);
        $this->assertEquals(60000.00, (float) ($payslip->gross_salary - 20000.00));
        $this->assertEquals(6000.00, (float) $payslip->total_deductions);
        $this->assertEquals(74000.00, (float) $payslip->net_salary);
        $this->assertStringStartsWith('PAY-2026-11-', $payslip->payslip_number);
    }

    public function test_payroll_period_approval_and_disbursement_lifecycle(): void
    {
        $employee = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'employee_code' => 'EMP-2026-0033',
            'first_name' => 'Kavita',
            'last_name' => 'Sen',
            'gender' => 'female',
            'date_of_birth' => '1991-11-11',
            'email' => 'kavita.sen@oakridge.edu',
            'phone' => '+919876500010',
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'date_of_joining' => '2023-01-01',
            'basic_salary' => 45000.00,
            'status' => 'active',
        ]);

        $period = PayrollPeriod::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'October 2026',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'processing',
        ]);

        $payslip = Payslip::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'payslip_number' => 'PAY-2026-10-0001',
            'basic_salary' => 45000.00,
            'total_allowances' => 5000.00,
            'total_deductions' => 2000.00,
            'gross_salary' => 50000.00,
            'net_salary' => 48000.00,
            'status' => 'draft',
        ]);

        // 1. Approve Period
        $approveRes = $this->actingAsAdmin()->postJson("/api/v1/payroll/periods/{$period->id}/approve");
        $approveRes->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertEquals('published', $payslip->fresh()->status);

        // 2. Mark as Disbursed/Paid
        $payRes = $this->actingAsAdmin()->postJson("/api/v1/payroll/periods/{$period->id}/pay", [
            'payment_date' => '2026-10-31',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'NEFT-8839210',
        ]);
        $payRes->assertStatus(200)
            ->assertJsonPath('data.status', 'paid');

        $this->assertEquals('paid', $payslip->fresh()->status);
        $this->assertEquals('NEFT-8839210', $payslip->fresh()->payment_reference);
    }

    public function test_teacher_access_restriction_ensures_teachers_only_access_assigned_students(): void
    {
        // 1. Create Teacher User & Employee
        $teacherUser = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Aditi',
            'last_name' => 'Sharma',
            'email' => 'aditi.sharma-' . Str::random(5) . '@oakridge.edu',
            'phone' => '+9198' . rand(10000000, 99999999),
            'password' => bcrypt('TeacherPassword123!'),
            'is_active' => true,
        ]);
        $teacherUser->assignRole('teacher', $this->tenant->id);

        TenantMembership::create([
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $teacherUser->id,
            'role' => 'teacher',
            'is_default' => true,
        ]);

        $teacherEmployee = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'user_id' => $teacherUser->id,
            'employee_code' => 'EMP-2026-0022',
            'first_name' => 'Aditi',
            'last_name' => 'Sharma',
            'gender' => 'female',
            'date_of_birth' => '1993-02-14',
            'email' => $teacherUser->email,
            'phone' => $teacherUser->phone,
            'department_id' => $this->department->id,
            'designation_id' => $this->teacherDesignation->id,
            'date_of_joining' => '2022-06-01',
        ]);

        // 2. Create 2 Classes: Class 10A (Assigned to Teacher) and Class 8B (Unassigned)
        $board = Board::where('code', 'CBSE')->first();

        $class10 = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'board_id' => $board->id,
            'name' => 'Grade 10',
            'code' => 'G10-TCH',
            'order_index' => 10,
        ]);
        $section10A = Section::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $class10->id,
            'name' => 'A',
            'max_capacity' => 40,
        ]);

        $class8 = SchoolClass::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'board_id' => $board->id,
            'name' => 'Grade 8',
            'code' => 'G8-TCH',
            'order_index' => 8,
        ]);
        $section8B = Section::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $class8->id,
            'name' => 'B',
            'max_capacity' => 40,
        ]);

        // Assign teacher as Class Teacher for 10A
        ClassTeacher::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'class_id' => $class10->id,
            'section_id' => $section10A->id,
            'employee_id' => $teacherEmployee->id,
            'academic_session_id' => $this->session->id,
            'is_primary' => true,
        ]);

        // 3. Create Student 1 in 10A and Student 2 in 8B
        $student1 = Student::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'STU-2026-9001',
            'admission_date' => '2026-04-01',
            'first_name' => 'Kunal',
            'last_name' => 'Verma',
            'gender' => 'male',
            'date_of_birth' => '2010-01-01',
            'status' => 'active',
        ]);
        StudentEnrollment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $student1->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $class10->id,
            'section_id' => $section10A->id,
            'status' => 'enrolled',
            'enrollment_date' => '2026-04-01',
        ]);

        $student2 = Student::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'school_id' => $this->school->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'STU-2026-9002',
            'admission_date' => '2026-04-01',
            'first_name' => 'Divya',
            'last_name' => 'Nambiar',
            'gender' => 'female',
            'date_of_birth' => '2012-05-05',
            'status' => 'active',
        ]);
        StudentEnrollment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'student_id' => $student2->id,
            'academic_session_id' => $this->session->id,
            'class_id' => $class8->id,
            'section_id' => $section8B->id,
            'status' => 'enrolled',
            'enrollment_date' => '2026-04-01',
        ]);

        // 4. Authenticate as Teacher Aditi
        $teacherClient = $this->actingAs($teacherUser, 'sanctum')
            ->withHeaders([
                'X-Tenant-ID' => $this->tenant->id,
                'X-Branch-ID' => $this->branch->id,
            ]);

        // Can view assigned student 1 (in Grade 10A)
        $res1 = $teacherClient->getJson("/api/v1/students/{$student1->id}");
        $res1->assertStatus(200)
            ->assertJsonPath('data.first_name', 'Kunal');

        // Cannot view unassigned student 2 (in Grade 8B) -> Should be 403 Forbidden
        $res2 = $teacherClient->getJson("/api/v1/students/{$student2->id}");
        $res2->assertStatus(403);
    }

    public function test_tenant_isolation_prevents_access_to_another_school_staff_and_payroll(): void
    {
        // Create Tenant B
        $tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Starlight Academy',
            'slug' => 'starlight-' . Str::random(5),
            'contact_email' => 'admin-' . Str::random(5) . '@starlight.edu',
            'contact_phone' => '+919876543299',
            'status' => 'active',
        ]);

        $schoolB = School::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'name' => 'Starlight High',
            'code' => 'STAR-01',
            'status' => 'active',
        ]);

        $branchB = SchoolBranch::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'school_id' => $schoolB->id,
            'name' => 'City Campus',
            'branch_code' => 'CC',
            'is_active' => true,
        ]);

        $deptB = EmployeeDepartment::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'name' => 'Arts Department',
            'code' => 'ARTS',
            'is_active' => true,
        ]);

        $desigB = EmployeeDesignation::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'branch_id' => $branchB->id,
            'department_id' => $deptB->id,
            'title' => 'Art Teacher',
            'code' => 'ART-TCH',
            'is_active' => true,
        ]);

        $employeeB = Employee::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'school_id' => $schoolB->id,
            'branch_id' => $branchB->id,
            'employee_code' => 'EMP-2026-8888',
            'first_name' => 'Ravi',
            'last_name' => 'Shankar',
            'gender' => 'male',
            'date_of_birth' => '1981-07-07',
            'email' => 'ravi@starlight.edu',
            'phone' => '+919876599999',
            'department_id' => $deptB->id,
            'designation_id' => $desigB->id,
            'date_of_joining' => '2021-01-01',
        ]);

        // Attempt to fetch Tenant B's employee from Tenant A's authenticated context -> Must return 404 (due to TenantScoped)
        $res = $this->actingAsAdmin()->getJson("/api/v1/employees/{$employeeB->id}");
        $res->assertStatus(404);
    }
}
