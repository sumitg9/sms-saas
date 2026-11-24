<?php

namespace Database\Seeders;

use App\Models\AcademicCalendar;
use App\Models\AcademicEvent;
use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\Board;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\ClassLevel;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\EmployeeSalaryProfile;
use App\Models\Examination;
use App\Models\ExaminationType;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\FeeCategory;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\FeePayment;
use App\Models\FeePaymentAllocation;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\GradeRange;
use App\Models\GradingScheme;
use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\HostelAllocation;
use App\Models\HostelBuilding;
use App\Models\HostelRoom;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventoryStockTransaction;
use App\Models\InventoryVendor;
use App\Models\LearningResource;
use App\Models\LibraryBook;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\StudentMark;
use App\Models\StudentTransportAllocation;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TeacherSubject;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TransportDriver;
use App\Models\TransportRoute;
use App\Models\TransportStop;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Models\UserRole;
use App\Models\Visitor;
use App\Models\VisitorPass;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MultiSchoolDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Enable Super Admin context to seed cross-tenant data cleanly without RLS blocking
        $context = app(TenantContext::class);
        $context->setSuperAdmin(true);

        $boards = Board::all()->keyBy('code');
        $roles = Role::whereNull('tenant_id')->get()->keyBy('name');
        $growthPlan = SubscriptionPlan::where('slug', 'growth')->first();
        $enterprisePlan = SubscriptionPlan::where('slug', 'enterprise')->first();

        DB::transaction(function () use ($boards, $roles, $growthPlan, $enterprisePlan) {
            // =========================================================================
            // SCHOOL 1: Apex International Public School (CBSE)
            // =========================================================================
            $this->command?->info("Seeding / Updating School 1: Apex International Public School...");
            
            $tenant1 = Tenant::updateOrCreate(
                ['slug' => 'apex-education-trust'],
                [
                    'name' => 'Apex Educational Trust',
                    'organization_type' => 'trust',
                    'contact_email' => 'info@apextrust.edu',
                    'contact_phone' => '+919876543210',
                    'currency' => 'INR',
                    'locale' => 'en',
                    'timezone' => 'Asia/Kolkata',
                    'status' => 'active',
                    'billing_address' => [
                        'line1' => 'Plot 42, Institutional Area, Knowledge Park III',
                        'city' => 'Greater Noida',
                        'state' => 'Uttar Pradesh',
                        'country' => 'India',
                        'postal_code' => '201306',
                    ],
                ]
            );

            if ($growthPlan) {
                Subscription::updateOrCreate(
                    ['tenant_id' => $tenant1->id],
                    [
                        'plan_id' => $growthPlan->id,
                        'billing_cycle' => 'annual',
                        'status' => 'active',
                        'current_period_start' => now()->startOfYear(),
                        'current_period_end' => now()->endOfYear(),
                    ]
                );
            }

            $school1 = School::updateOrCreate(
                ['tenant_id' => $tenant1->id, 'code' => 'APEX-INTL'],
                [
                    'name' => 'Apex International Public School',
                    'affiliation_number' => 'CBSE-2130891',
                    'education_board' => 'CBSE',
                    'email' => 'contact@apexschool.edu',
                    'phone' => '+919876543211',
                    'address' => ['city' => 'Greater Noida', 'state' => 'Uttar Pradesh', 'country' => 'India'],
                    'is_active' => true,
                ]
            );

            $branch1 = SchoolBranch::updateOrCreate(
                ['school_id' => $school1->id, 'branch_code' => 'MAIN-01'],
                [
                    'tenant_id' => $tenant1->id,
                    'name' => 'North Main Campus',
                    'campus_type' => 'main',
                    'phone' => '+919876543212',
                    'email' => 'north@apexschool.edu',
                    'is_active' => true,
                ]
            );

            $this->seedSchoolFullOperationalData(
                tenant: $tenant1,
                school: $school1,
                branch: $branch1,
                board: $boards['CBSE'] ?? null,
                roles: $roles,
                domainPrefix: 'apexschool.edu',
                schoolTag: 'APEX',
                ownerName: 'Rajendra Verma',
                adminName: 'Vikram Aditya',
                principalName: 'Sunil Sharma',
                teacherName: 'Ananya Iyer',
                accountantName: 'Ramesh Gupta',
                studentName: 'Aarav Sharma',
                parentName: 'Sanjay Sharma',
                curriculumNames: ['English', 'Mathematics', 'Science', 'Social Studies', 'Hindi', 'Computer Applications'],
                hostelName: 'Tagore Boys Hostel',
                busRegistration: 'UP-16-AX-2026'
            );

            // =========================================================================
            // SCHOOL 2: Beacon Heights World School (ICSE / Cambridge)
            // =========================================================================
            $this->command?->info("Seeding School 2: Beacon Heights World School...");

            $tenant2 = Tenant::updateOrCreate(
                ['slug' => 'beacon-heights-trust'],
                [
                    'name' => 'Beacon Heights Academy Trust',
                    'organization_type' => 'society',
                    'contact_email' => 'contact@beacontrust.org',
                    'contact_phone' => '+919876544000',
                    'currency' => 'INR',
                    'locale' => 'en',
                    'timezone' => 'Asia/Kolkata',
                    'status' => 'active',
                    'billing_address' => [
                        'line1' => 'Beacon Enclave, Outer Ring Road, Bellandur',
                        'city' => 'Bengaluru',
                        'state' => 'Karnataka',
                        'country' => 'India',
                        'postal_code' => '560103',
                    ],
                ]
            );

            if ($enterprisePlan) {
                Subscription::updateOrCreate(
                    ['tenant_id' => $tenant2->id],
                    [
                        'plan_id' => $enterprisePlan->id,
                        'billing_cycle' => 'annual',
                        'status' => 'active',
                        'current_period_start' => now()->startOfYear(),
                        'current_period_end' => now()->endOfYear(),
                    ]
                );
            }

            $school2 = School::updateOrCreate(
                ['tenant_id' => $tenant2->id, 'code' => 'BEACON-WORLD'],
                [
                    'name' => 'Beacon Heights World School',
                    'affiliation_number' => 'ICSE-KA092',
                    'education_board' => 'ICSE',
                    'email' => 'admissions@beaconschool.edu',
                    'phone' => '+919876544100',
                    'address' => ['city' => 'Bengaluru', 'state' => 'Karnataka', 'country' => 'India'],
                    'is_active' => true,
                ]
            );

            $branch2 = SchoolBranch::updateOrCreate(
                ['school_id' => $school2->id, 'branch_code' => 'SOUTH-01'],
                [
                    'tenant_id' => $tenant2->id,
                    'name' => 'South Central Campus',
                    'campus_type' => 'main',
                    'phone' => '+919876544200',
                    'email' => 'south@beaconschool.edu',
                    'is_active' => true,
                ]
            );

            $this->seedSchoolFullOperationalData(
                tenant: $tenant2,
                school: $school2,
                branch: $branch2,
                board: $boards['ICSE'] ?? $boards['CBSE'] ?? null,
                roles: $roles,
                domainPrefix: 'beaconschool.edu',
                schoolTag: 'BEACON',
                ownerName: 'Dr. Meenakshi Sundaram',
                adminName: 'Rohan Mehra',
                principalName: 'Margaret D\'Souza',
                teacherName: 'Devendra Joshi',
                accountantName: 'Kavita Nair',
                studentName: 'Rhea Sundaram',
                parentName: 'Sundaram Natarajan',
                curriculumNames: ['English Literature', 'Mathematics', 'Physics', 'Chemistry', 'Economics', 'Robotics & AI'],
                hostelName: 'Sarojini Girls Hostel',
                busRegistration: 'KA-01-BH-2026'
            );
        });

        $this->command?->info("Multi-School Demo Seeder executed successfully!");
    }

    /**
     * Seeds complete, realistic, isolated operational data for an institution.
     */
    private function seedSchoolFullOperationalData(
        Tenant $tenant,
        School $school,
        SchoolBranch $branch,
        ?Board $board,
        $roles,
        string $domainPrefix,
        string $schoolTag,
        string $ownerName,
        string $adminName,
        string $principalName,
        string $teacherName,
        string $accountantName,
        string $studentName,
        string $parentName,
        array $curriculumNames,
        string $hostelName,
        string $busRegistration
    ): void {
        $password = Hash::make('Password123!');

        // 1. Core Users & Memberships
        $users = [
            'owner' => ['email' => "owner@{$domainPrefix}", 'name' => $ownerName, 'role' => 'school_owner', 'is_owner' => true],
            'admin' => ['email' => "admin@{$domainPrefix}", 'name' => $adminName, 'role' => 'school_admin', 'is_owner' => false],
            'principal' => ['email' => "principal@{$domainPrefix}", 'name' => $principalName, 'role' => 'principal', 'is_owner' => false],
            'teacher' => ['email' => "teacher@{$domainPrefix}", 'name' => $teacherName, 'role' => 'teacher', 'is_owner' => false],
            'accountant' => ['email' => "accountant@{$domainPrefix}", 'name' => $accountantName, 'role' => 'accountant', 'is_owner' => false],
            'student' => ['email' => "student@{$domainPrefix}", 'name' => $studentName, 'role' => 'student', 'is_owner' => false],
            'parent' => ['email' => "parent@{$domainPrefix}", 'name' => $parentName, 'role' => 'parent', 'is_owner' => false],
        ];

        $userModels = [];
        foreach ($users as $key => $uInfo) {
            $nameParts = explode(' ', $uInfo['name'], 2);
            $u = User::updateOrCreate(
                ['email' => $uInfo['email']],
                [
                    'first_name' => $nameParts[0],
                    'last_name' => $nameParts[1] ?? '',
                    'phone' => '+91' . rand(7000000000, 9999999999),
                    'email_verified_at' => now(),
                    'password' => $password,
                    'is_active' => true,
                    'is_platform_admin' => false,
                ]
            );
            $userModels[$key] = $u;

            TenantMembership::updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $u->id],
                ['school_id' => $school->id, 'branch_id' => $branch->id, 'is_owner' => $uInfo['is_owner'], 'is_active' => true]
            );

            if (isset($roles[$uInfo['role']])) {
                UserRole::updateOrCreate(
                    ['user_id' => $u->id, 'tenant_id' => $tenant->id],
                    ['role_id' => $roles[$uInfo['role']]->id, 'branch_id' => $branch->id]
                );
            }
        }

        // 2. Academic Session
        $session = AcademicSession::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => '2026-2027'],
            [
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'is_current' => true,
            ]
        );

        // Academic Calendar
        $calendar = AcademicCalendar::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => "{$schoolTag} Annual Academic Calendar 2026-27"],
            [
                'tenant_id' => $tenant->id,
                'academic_session_id' => $session->id,
                'is_active' => true,
            ]
        );

        AcademicEvent::updateOrCreate(
            ['calendar_id' => $calendar->id, 'title' => 'Annual Sports Meet 2026'],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'event_type' => 'sports',
                'start_date' => '2026-11-28',
                'end_date' => '2026-11-29',
                'description' => 'Inter-house athletic meet and sports competition.',
            ]
        );

        // 3. Class Levels, Classes & Sections
        $secLevel = ClassLevel::updateOrCreate(
            ['school_id' => $school->id, 'name' => 'Secondary'],
            ['tenant_id' => $tenant->id, 'order_index' => 1]
        );

        $grade10 = SchoolClass::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => "{$schoolTag}-G10"],
            [
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'class_level_id' => $secLevel->id,
                'board_id' => $board?->id,
                'name' => 'Grade 10',
                'order_index' => 10,
                'is_active' => true,
            ]
        );

        $secA = Section::updateOrCreate(
            ['class_id' => $grade10->id, 'name' => 'Section A'],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'room_number' => "ROOM-101",
                'max_capacity' => 40,
                'is_active' => true,
            ]
        );

        // 4. Staff (Created before subject mapping for teacher foreign keys)
        $acadDept = EmployeeDepartment::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => "{$schoolTag}-ACAD"],
            ['tenant_id' => $tenant->id, 'name' => 'Academic Faculty', 'is_active' => true]
        );

        $teacherDesig = EmployeeDesignation::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => "{$schoolTag}-TGT"],
            ['tenant_id' => $tenant->id, 'department_id' => $acadDept->id, 'title' => 'Senior Educator', 'level' => 5, 'is_active' => true]
        );

        $employeeTeacher = Employee::updateOrCreate(
            ['branch_id' => $branch->id, 'employee_code' => "{$schoolTag}-EMP-01"],
            [
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'user_id' => $userModels['teacher']->id,
                'first_name' => $userModels['teacher']->first_name,
                'last_name' => $userModels['teacher']->last_name,
                'gender' => 'female',
                'date_of_birth' => '1985-06-15',
                'email' => $userModels['teacher']->email,
                'phone' => $userModels['teacher']->phone,
                'department_id' => $acadDept->id,
                'designation_id' => $teacherDesig->id,
                'employment_type' => 'permanent',
                'date_of_joining' => '2022-04-01',
                'basic_salary' => 65000.00,
                'status' => 'active',
            ]
        );

        EmployeeSalaryProfile::updateOrCreate(
            ['employee_id' => $employeeTeacher->id],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'base_salary' => 65000.00,
                'payment_method' => 'bank_transfer',
                'custom_components' => ['hra' => 15000, 'da' => 5000],
                'is_active' => true,
            ]
        );

        // Staff Attendance
        EmployeeAttendance::updateOrCreate(
            ['employee_id' => $employeeTeacher->id, 'date' => now()->toDateString()],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'status' => 'present',
                'check_in_time' => '08:15:00',
                'check_out_time' => '16:00:00',
                'source' => 'manual',
            ]
        );

        // Payroll Period & Payslip
        $period = PayrollPeriod::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => "October 2026 ({$schoolTag})"],
            [
                'tenant_id' => $tenant->id,
                'academic_session_id' => $session->id,
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-31',
                'payment_date' => '2026-11-01',
                'status' => 'paid',
                'total_gross' => 85000.00,
                'total_deductions' => 3800.00,
                'total_net' => 81200.00,
            ]
        );

        Payslip::updateOrCreate(
            ['employee_id' => $employeeTeacher->id, 'payroll_period_id' => $period->id],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'payslip_number' => "PAY-{$schoolTag}-202610-01",
                'basic_salary' => 65000.00,
                'total_allowances' => 20000.00,
                'total_deductions' => 3800.00,
                'gross_salary' => 85000.00,
                'net_salary' => 81200.00,
                'working_days' => 26,
                'present_days' => 25,
                'paid_leaves' => 1,
                'unpaid_leaves' => 0,
                'status' => 'paid',
                'payment_method' => 'bank_transfer',
            ]
        );

        // 5. Subjects & Mapping
        $subjectModels = [];
        foreach ($curriculumNames as $idx => $sName) {
            $code = strtoupper(substr($sName, 0, 3)) . "-10";
            $subjectModels[] = Subject::updateOrCreate(
                ['branch_id' => $branch->id, 'code' => "{$schoolTag}-{$code}"],
                [
                    'tenant_id' => $tenant->id,
                    'school_id' => $school->id,
                    'name' => $sName,
                    'type' => $idx === 2 ? 'practical' : 'theory',
                    'credit_hours' => 4.0,
                    'is_active' => true,
                ]
            );
        }

        // Map class subjects & assign teacher
        foreach ($subjectModels as $sub) {
            $cs = ClassSubject::updateOrCreate(
                ['class_id' => $grade10->id, 'subject_id' => $sub->id],
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'academic_session_id' => $session->id,
                    'is_elective' => false,
                ]
            );

            TeacherSubject::updateOrCreate(
                ['employee_id' => $employeeTeacher->id, 'class_subject_id' => $cs->id],
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'is_primary' => true,
                ]
            );
        }

        ClassTeacher::updateOrCreate(
            ['class_id' => $grade10->id, 'section_id' => $secA->id, 'academic_session_id' => $session->id],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'employee_id' => $employeeTeacher->id,
                'is_primary' => true,
            ]
        );

        // 6. Student & Enrollment
        $student = Student::updateOrCreate(
            ['branch_id' => $branch->id, 'admission_number' => "ADM-{$schoolTag}-2026-001"],
            [
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'user_id' => $userModels['student']->id,
                'admission_date' => '2026-04-01',
                'first_name' => $userModels['student']->first_name,
                'last_name' => $userModels['student']->last_name,
                'gender' => 'male',
                'date_of_birth' => '2011-04-10',
                'nationality' => 'Indian',
                'caste_category' => 'General',
                'emergency_contact_name' => $userModels['parent']->name,
                'emergency_contact_phone' => $userModels['parent']->phone,
                'status' => 'active',
            ]
        );

        $enrollment = StudentEnrollment::updateOrCreate(
            ['student_id' => $student->id, 'academic_session_id' => $session->id],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'class_id' => $grade10->id,
                'section_id' => $secA->id,
                'roll_number' => '1',
                'enrollment_date' => '2026-04-01',
                'status' => 'enrolled',
            ]
        );

        // Guardian relationship
        $guardian = Guardian::updateOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => $userModels['parent']->phone],
            [
                'user_id' => $userModels['parent']->id,
                'first_name' => $userModels['parent']->first_name,
                'last_name' => $userModels['parent']->last_name,
                'relation_type' => 'father',
                'email' => $userModels['parent']->email,
                'occupation' => 'Corporate Executive',
                'annual_income' => 1800000.00,
            ]
        );

        StudentGuardian::updateOrCreate(
            ['student_id' => $student->id, 'guardian_id' => $guardian->id],
            [
                'tenant_id' => $tenant->id,
                'is_primary' => true,
                'has_pickup_rights' => true,
                'receives_invoices' => true,
                'emergency_contact_priority' => 1,
            ]
        );

        // 7. Student Daily Attendance
        for ($day = 1; $day <= 10; $day++) {
            $attDate = Carbon::create(2026, 11, $day);
            if ($attDate->isWeekend()) continue;

            StudentAttendance::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'date' => $attDate->toDateString(),
                ],
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'student_id' => $student->id,
                    'class_id' => $grade10->id,
                    'section_id' => $secA->id,
                    'status' => $day === 3 ? 'absent' : ($day === 6 ? 'late' : 'present'),
                    'remarks' => $day === 3 ? 'Sick leave requested by parent' : 'On time',
                    'marked_by' => $userModels['teacher']->id,
                ]
            );
        }

        // 8. Fee Management: Structure, Invoice & Payment
        $feeCat = FeeCategory::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => "Annual Composite Fee ({$schoolTag})"],
            ['tenant_id' => $tenant->id, 'description' => 'Tuition, Lab and Activity Charges', 'is_active' => true]
        );

        $feeStructure = FeeStructure::updateOrCreate(
            ['branch_id' => $branch->id, 'academic_session_id' => $session->id, 'class_id' => $grade10->id],
            [
                'tenant_id' => $tenant->id,
                'fee_category_id' => $feeCat->id,
                'name' => "Grade 10 Annual Comprehensive Fee 2026-27",
                'frequency' => 'annual',
                'total_amount' => 60000.00,
                'due_date' => '2026-08-31',
                'is_active' => true,
            ]
        );

        FeeStructureItem::updateOrCreate(
            ['fee_structure_id' => $feeStructure->id, 'fee_head_name' => 'Tuition Fee'],
            ['tenant_id' => $tenant->id, 'amount' => 45000.00, 'is_mandatory' => true]
        );
        FeeStructureItem::updateOrCreate(
            ['fee_structure_id' => $feeStructure->id, 'fee_head_name' => 'Smart Classroom & Lab Fee'],
            ['tenant_id' => $tenant->id, 'amount' => 15000.00, 'is_mandatory' => true]
        );

        // Generate Invoice
        $invoice = FeeInvoice::updateOrCreate(
            ['student_id' => $student->id, 'invoice_number' => "INV-{$schoolTag}-2026-001"],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'enrollment_id' => $enrollment->id,
                'academic_session_id' => $session->id,
                'subtotal' => 60000.00,
                'discount_amount' => 5000.00,
                'fine_amount' => 0.00,
                'total_amount' => 55000.00,
                'paid_amount' => 55000.00,
                'balance_amount' => 0.00,
                'due_date' => '2026-08-31',
                'status' => 'paid',
            ]
        );

        FeeInvoiceItem::updateOrCreate(
            ['fee_invoice_id' => $invoice->id, 'fee_head_name' => 'Tuition Fee'],
            ['tenant_id' => $tenant->id, 'amount' => 45000.00, 'paid_amount' => 45000.00]
        );

        // Record Settled Payment
        $payment = FeePayment::updateOrCreate(
            ['receipt_number' => "RCP-{$schoolTag}-2026-001"],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'student_id' => $student->id,
                'amount_paid' => 55000.00,
                'payment_date' => '2026-08-15 10:30:00',
                'payment_method' => 'upi',
                'reference_number' => "UPI-{$schoolTag}-9812739",
                'collected_by' => $userModels['accountant']->id,
                'status' => 'cleared',
                'notes' => 'Full fee settlement with early-bird discount.',
            ]
        );

        FeePaymentAllocation::updateOrCreate(
            ['fee_payment_id' => $payment->id, 'fee_invoice_id' => $invoice->id],
            ['tenant_id' => $tenant->id, 'allocated_amount' => 55000.00]
        );

        // 9. Examination & Marks
        $examType = ExaminationType::updateOrCreate(
            ['school_id' => $school->id, 'name' => "Mid-Term Examination ({$schoolTag})"],
            [
                'tenant_id' => $tenant->id,
                'weightage_percent' => 30.00,
                'is_active' => true,
            ]
        );

        $gradingScheme = GradingScheme::updateOrCreate(
            ['school_id' => $school->id, 'name' => "{$schoolTag} Standard 10-Point Grading"],
            [
                'tenant_id' => $tenant->id,
                'scheme_type' => 'percentage',
                'description' => 'Standard percentage based grading',
            ]
        );

        $ranges = [
            ['grade' => 'A1', 'min' => 91, 'max' => 100, 'point' => 10],
            ['grade' => 'A2', 'min' => 81, 'max' => 90, 'point' => 9],
            ['grade' => 'B1', 'min' => 71, 'max' => 80, 'point' => 8],
            ['grade' => 'B2', 'min' => 61, 'max' => 70, 'point' => 7],
            ['grade' => 'C1', 'min' => 51, 'max' => 60, 'point' => 6],
            ['grade' => 'D',  'min' => 40, 'max' => 50, 'point' => 5],
            ['grade' => 'E',  'min' => 0,  'max' => 39, 'point' => 0],
        ];
        foreach ($ranges as $r) {
            GradeRange::updateOrCreate(
                ['grading_scheme_id' => $gradingScheme->id, 'grade_name' => $r['grade']],
                [
                    'min_percentage' => $r['min'],
                    'max_percentage' => $r['max'],
                    'grade_point' => $r['point'],
                    'description' => "Grade {$r['grade']}",
                ]
            );
        }

        $exam = Examination::updateOrCreate(
            ['branch_id' => $branch->id, 'title' => "Term 1 Mid-Term Examination 2026 ({$schoolTag})"],
            [
                'tenant_id' => $tenant->id,
                'academic_session_id' => $session->id,
                'exam_type_id' => $examType->id,
                'grading_scheme_id' => $gradingScheme->id,
                'start_date' => '2026-09-15',
                'end_date' => '2026-09-25',
                'is_published' => true,
                'published_at' => '2026-10-01 12:00:00',
            ]
        );

        $totalMarksObtained = 0;
        $totalMaxMarks = 0;

        foreach ($subjectModels as $sub) {
            $schedule = ExamSchedule::updateOrCreate(
                ['examination_id' => $exam->id, 'subject_id' => $sub->id, 'class_id' => $grade10->id],
                [
                    'tenant_id' => $tenant->id,
                    'exam_date' => '2026-09-18',
                    'start_time' => '09:00:00',
                    'end_time' => '12:00:00',
                    'room_number' => 'HALL-A',
                    'max_marks' => 100.00,
                    'pass_marks' => 40.00,
                ]
            );

            $markVal = rand(78, 96);
            $totalMarksObtained += $markVal;
            $totalMaxMarks += 100;

            StudentMark::updateOrCreate(
                ['exam_schedule_id' => $schedule->id, 'student_id' => $student->id],
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'enrollment_id' => $enrollment->id,
                    'marks_obtained' => $markVal,
                    'is_absent' => false,
                    'entered_by' => $userModels['teacher']->id,
                ]
            );
        }

        $overallPct = ($totalMarksObtained / $totalMaxMarks) * 100;
        ExamResult::updateOrCreate(
            ['examination_id' => $exam->id, 'student_id' => $student->id],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'enrollment_id' => $enrollment->id,
                'total_marks' => $totalMaxMarks,
                'marks_obtained' => $totalMarksObtained,
                'percentage' => $overallPct,
                'grade' => $overallPct >= 90 ? 'A1' : 'A2',
                'grade_point' => 10,
                'rank' => 1,
                'result_status' => 'pass',
            ]
        );

        // 10. Homework & LMS
        $homework = Homework::updateOrCreate(
            ['branch_id' => $branch->id, 'title' => "Chapter 4 Case Study & Numericals ({$schoolTag})"],
            [
                'tenant_id' => $tenant->id,
                'class_id' => $grade10->id,
                'section_id' => $secA->id,
                'subject_id' => $subjectModels[0]->id,
                'teacher_id' => $employeeTeacher->id,
                'description' => 'Complete textual questions 1 through 10 and attach the handwritten notes.',
                'assigned_date' => '2026-11-10',
                'due_date' => '2026-11-25',
                'max_marks' => 25.00,
                'is_active' => true,
            ]
        );

        HomeworkSubmission::updateOrCreate(
            ['homework_id' => $homework->id, 'student_id' => $student->id],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'submitted_at' => '2026-11-12 18:00:00',
                'submission_text' => 'All numerical problems completed with detailed working steps.',
                'attachments' => ['documents/homework_ch4_solved.pdf'],
                'marks_obtained' => 24.00,
                'status' => 'graded',
                'feedback' => 'Excellent problem solving and clear presentation.',
                'graded_by' => $userModels['teacher']->id,
            ]
        );

        LearningResource::updateOrCreate(
            ['branch_id' => $branch->id, 'title' => "Comprehensive Revision Notes & Formula Sheet ({$schoolTag})"],
            [
                'tenant_id' => $tenant->id,
                'class_id' => $grade10->id,
                'subject_id' => $subjectModels[1]->id,
                'uploaded_by' => $userModels['teacher']->id,
                'resource_type' => 'document',
                'file_url' => 'study_materials/math_formulas_grade10.pdf',
                'description' => 'Formula sheet covering Algebra, Trigonometry, and Coordinate Geometry.',
                'is_published' => true,
            ]
        );

        // 11. Announcements & Communication
        Announcement::updateOrCreate(
            ['branch_id' => $branch->id, 'title' => "Welcome to Academic Session 2026-27 - {$school->name}"],
            [
                'tenant_id' => $tenant->id,
                'content' => "We are delighted to welcome all students, faculty and parents. Regular classes and laboratories are fully operational.",
                'target_audience' => 'all',
                'priority' => 'normal',
                'published_at' => '2026-04-05 09:00:00',
                'author_id' => $userModels['principal']->id,
                'is_published' => true,
                'requires_acknowledgement' => false,
            ]
        );

        // 12. Library Management
        $book = LibraryBook::updateOrCreate(
            ['branch_id' => $branch->id, 'isbn' => "978-013235088-{$schoolTag}"],
            [
                'tenant_id' => $tenant->id,
                'title' => "Principles of Modern Science & Technology ({$schoolTag})",
                'author' => 'Robert C. Martin & Team',
                'publisher' => 'Pearson Education',
                'edition' => '3rd Edition',
                'category' => 'Science',
                'total_copies' => 10,
                'available_copies' => 9,
                'shelf_no' => 'SCI-B4',
            ]
        );

        $bookCopy = BookCopy::updateOrCreate(
            ['book_id' => $book->id, 'barcode' => "BC-{$schoolTag}-001"],
            [
                'tenant_id' => $tenant->id,
                'copy_number' => 1,
                'status' => 'issued',
            ]
        );

        BookIssue::updateOrCreate(
            ['copy_id' => $bookCopy->id, 'member_id' => $student->id],
            [
                'tenant_id' => $tenant->id,
                'member_type' => 'student',
                'issue_date' => '2026-11-05',
                'due_date' => '2026-11-20',
                'return_date' => null,
                'fine_amount' => 0.00,
                'fine_paid' => 0.00,
                'status' => 'issued',
                'issued_by' => $userModels['admin']->id,
            ]
        );

        // 13. Transport Management
        $vehicle = TransportVehicle::updateOrCreate(
            ['branch_id' => $branch->id, 'registration_number' => $busRegistration],
            [
                'tenant_id' => $tenant->id,
                'vehicle_model' => 'Tata Starbus 42-Seater',
                'capacity' => 42,
                'fitness_certificate_expiry' => '2028-05-31',
                'insurance_expiry' => '2027-06-30',
                'gps_device_id' => "GPS-{$schoolTag}-001",
                'is_active' => true,
            ]
        );

        $driver = TransportDriver::updateOrCreate(
            ['tenant_id' => $tenant->id, 'license_number' => "DL-{$schoolTag}-20199128"],
            [
                'employee_id' => $employeeTeacher->id,
                'license_expiry' => '2030-01-01',
                'phone' => '+919876549901',
                'is_active' => true,
            ]
        );

        $route = TransportRoute::updateOrCreate(
            ['branch_id' => $branch->id, 'route_name' => "Sector 62 to {$schoolTag} Campus"],
            [
                'tenant_id' => $tenant->id,
                'start_point' => 'Sector 62 Metro Hub',
                'end_point' => "{$school->name} Campus",
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'fare' => 3200.00,
                'is_active' => true,
            ]
        );

        $stop = TransportStop::updateOrCreate(
            ['route_id' => $route->id, 'stop_name' => 'Gaur City Circle'],
            [
                'tenant_id' => $tenant->id,
                'stop_order' => 1,
                'pickup_time' => '07:20:00',
                'drop_time' => '15:40:00',
                'fare_amount' => 3200.00,
            ]
        );

        StudentTransportAllocation::updateOrCreate(
            ['student_id' => $student->id, 'route_id' => $route->id],
            [
                'tenant_id' => $tenant->id,
                'enrollment_id' => $enrollment->id,
                'stop_id' => $stop->id,
                'academic_session_id' => $session->id,
                'status' => 'active',
            ]
        );

        // 14. Inventory Management
        $invCat = InventoryCategory::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => "Science & Robotics Lab ({$schoolTag})"],
            [
                'tenant_id' => $tenant->id,
                'code' => "LAB-{$schoolTag}",
            ]
        );

        $vendor = InventoryVendor::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => "Scientific Supplies & Robotics Ltd ({$schoolTag})"],
            [
                'contact_person' => 'Girish Menon',
                'phone' => '+919822334455',
                'email' => "supplies@scientific{$schoolTag}.com",
                'address' => 'Industrial Area Phase 2',
                'gst_number' => "GSTIN{$schoolTag}992384",
            ]
        );

        $invItem = InventoryItem::updateOrCreate(
            ['branch_id' => $branch->id, 'item_code' => "ITM-{$schoolTag}-01"],
            [
                'tenant_id' => $tenant->id,
                'category_id' => $invCat->id,
                'item_name' => 'Microcontroller Starter Kit (Arduino/ESP32)',
                'unit_of_measure' => 'sets',
                'min_stock_alert' => 5,
                'current_stock' => 50,
            ]
        );

        $invPurchase = InventoryPurchase::updateOrCreate(
            ['branch_id' => $branch->id, 'invoice_number' => "PO-{$schoolTag}-2026-01"],
            [
                'tenant_id' => $tenant->id,
                'vendor_id' => $vendor->id,
                'total_amount' => 75000.00,
                'purchase_date' => '2026-07-10',
                'payment_status' => 'paid',
            ]
        );

        InventoryStockTransaction::updateOrCreate(
            ['item_id' => $invItem->id, 'reference_type' => 'purchase', 'reference_id' => $invPurchase->id],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'transaction_type' => 'purchase',
                'quantity' => 50,
                'balance_after' => 50,
                'unit_cost' => 1500.00,
                'created_by' => $userModels['admin']->id,
                'remarks' => 'Received against PO-01 in pristine condition.',
            ]
        );

        // 15. Hostel Management
        $hostel = HostelBuilding::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => "{$hostelName} ({$schoolTag})"],
            [
                'tenant_id' => $tenant->id,
                'building_type' => 'co_ed',
                'warden_name' => 'Col. K. S. Rathore (Retd.)',
                'warden_phone' => '+919811223344',
            ]
        );

        $room = HostelRoom::updateOrCreate(
            ['building_id' => $hostel->id, 'room_number' => 'H-201'],
            [
                'tenant_id' => $tenant->id,
                'room_type' => 'double',
                'capacity' => 2,
                'occupied_beds' => 1,
                'fee_per_session' => 12000.00,
                'is_active' => true,
            ]
        );

        HostelAllocation::updateOrCreate(
            ['student_id' => $student->id, 'room_id' => $room->id],
            [
                'tenant_id' => $tenant->id,
                'enrollment_id' => $enrollment->id,
                'bed_number' => 'B-1',
                'allocation_date' => '2026-07-01',
                'status' => 'active',
            ]
        );

        // 16. Visitor Management
        $visitor = Visitor::updateOrCreate(
            ['branch_id' => $branch->id, 'phone' => '+919876540099'],
            [
                'tenant_id' => $tenant->id,
                'full_name' => 'Prakash Chawla (Govt Education Inspector)',
                'national_id' => 'AADHAAR-8921-9981-2211',
                'address' => 'Ministry of Education, Regional Office',
            ]
        );

        VisitorPass::updateOrCreate(
            ['visitor_id' => $visitor->id, 'pass_number' => "PASS-{$schoolTag}-2026-001"],
            [
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'purpose' => 'Academic Affiliation Compliance Inspection',
                'host_employee_id' => $employeeTeacher->id,
                'check_in_time' => '2026-11-20 10:00:00',
                'check_out_time' => '2026-11-20 13:30:00',
                'status' => 'checked_out',
            ]
        );

        // 17. Certificate Templates & Issued Certificate
        $bonafideTpl = CertificateTemplate::updateOrCreate(
            ['branch_id' => $branch->id, 'type' => 'bonafide'],
            [
                'tenant_id' => $tenant->id,
                'title' => "Bonafide Student Certificate - {$school->name}",
                'body_template' => "This is to certify that {{student_name}}, son/daughter of {{guardian_name}}, is a bonafide student of {{school_name}}, studying in {{class_name}} {{section_name}} during the academic year {{academic_session}}.",
                'signature_title_1' => 'Class Teacher',
                'signature_title_2' => 'Principal',
                'is_active' => true,
            ]
        );

        CertificateTemplate::updateOrCreate(
            ['branch_id' => $branch->id, 'type' => 'transfer'],
            [
                'tenant_id' => $tenant->id,
                'title' => "Transfer Certificate (TC) - {$school->name}",
                'body_template' => "This certifies that {{student_name}} (Admission No: {{admission_number}}) has completed his/her studies at {{school_name}} with satisfactory conduct. All dues have been cleared.",
                'signature_title_1' => 'Head Clerk / Accountant',
                'signature_title_2' => 'Principal / Director',
                'is_active' => true,
            ]
        );

        Certificate::updateOrCreate(
            ['branch_id' => $branch->id, 'certificate_number' => "CERT-{$schoolTag}-2026-001"],
            [
                'tenant_id' => $tenant->id,
                'template_id' => $bonafideTpl->id,
                'student_id' => $student->id,
                'academic_session_id' => $session->id,
                'type' => 'bonafide',
                'title' => "Bonafide Student Certificate - {$school->name}",
                'rendered_content' => "This is to certify that {$student->first_name} {$student->last_name} is a bonafide student of {$school->name}.",
                'recipient_name' => "{$student->first_name} {$student->last_name}",
                'issue_date' => '2026-11-01',
                'verification_code' => "VRF-{$schoolTag}-" . strtoupper(substr(md5($student->id), 0, 8)),
                'file_path' => "certificates/bonafide_{$schoolTag}_001.pdf",
                'status' => 'issued',
                'issued_by' => $userModels['principal']->id,
            ]
        );
    }
}
