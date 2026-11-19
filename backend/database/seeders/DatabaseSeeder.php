<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Board;
use App\Models\ClassLevel;
use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\PlanFeature;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. System Settings
        SystemSetting::updateOrCreate(
            ['group' => 'general', 'key' => 'platform_name'],
            ['value' => ['name' => 'EduCore SaaS - School ERP'], 'description' => 'Global SaaS platform name']
        );
        SystemSetting::updateOrCreate(
            ['group' => 'billing', 'key' => 'currency_defaults'],
            ['value' => ['currency' => 'INR', 'symbol' => '₹', 'tax_rate' => 18.0], 'description' => 'Platform billing defaults']
        );

        // 2. Standard Subscription Plans
        $starter = SubscriptionPlan::updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter Plan',
                'description' => 'Ideal for small single-campus schools up to 300 students.',
                'monthly_price' => 2999.00,
                'annual_price' => 29990.00,
                'currency' => 'INR',
                'max_students' => 300,
                'max_staff' => 50,
                'max_branches' => 1,
                'storage_limit_gb' => 10,
                'is_active' => true,
            ]
        );

        $growth = SubscriptionPlan::updateOrCreate(
            ['slug' => 'growth'],
            [
                'name' => 'Growth Plan',
                'description' => 'Comprehensive management for mid-sized schools up to 1,500 students.',
                'monthly_price' => 7999.00,
                'annual_price' => 79990.00,
                'currency' => 'INR',
                'max_students' => 1500,
                'max_staff' => 150,
                'max_branches' => 2,
                'storage_limit_gb' => 50,
                'is_active' => true,
            ]
        );

        $enterprise = SubscriptionPlan::updateOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise Plan',
                'description' => 'Unlimited capacity, multi-branch hierarchies, hostel and advanced HR/Payroll.',
                'monthly_price' => 19999.00,
                'annual_price' => 199990.00,
                'currency' => 'INR',
                'max_students' => -1, // Unlimited
                'max_staff' => 500,
                'max_branches' => 10,
                'storage_limit_gb' => 250,
                'is_active' => true,
            ]
        );

        // Plan features
        $featureModules = [
            'academic_core' => 'Core Academics & Classes',
            'student_management' => 'Student 360 Profiles',
            'attendance' => 'Student & Staff Attendance',
            'fee_management' => 'Fee Invoicing & Payments',
            'examinations' => 'Examinations & Report Cards',
            'timetable' => 'Timetable & Scheduling',
            'library' => 'Library Management',
            'transport' => 'Transport & Fleet GPS',
            'inventory' => 'Inventory & Procurement',
            'hostel' => 'Hostel & Mess Management',
            'payroll' => 'Staff HR & Payroll Processing',
            'portals' => 'Parent & Student Portals',
            'api_access' => 'REST API Access',
        ];

        foreach ($featureModules as $key => $name) {
            PlanFeature::updateOrCreate(
                ['plan_id' => $growth->id, 'feature_key' => $key],
                ['feature_name' => $name, 'is_enabled' => true, 'quota_limit' => null]
            );
            PlanFeature::updateOrCreate(
                ['plan_id' => $enterprise->id, 'feature_key' => $key],
                ['feature_name' => $name, 'is_enabled' => true, 'quota_limit' => null]
            );
        }

        // 3. System Permissions
        $modules = ['students', 'admissions', 'academics', 'attendance', 'fees', 'exams', 'staff', 'library', 'transport', 'reports'];
        $actions = ['view', 'create', 'edit', 'delete', 'approve', 'export', 'print'];

        $createdPermissions = [];
        foreach ($modules as $mod) {
            foreach ($actions as $act) {
                $permName = "{$mod}.{$act}";
                $createdPermissions[] = Permission::updateOrCreate(
                    ['name' => $permName],
                    [
                        'module' => $mod,
                        'display_name' => ucfirst($act) . ' ' . ucfirst($mod),
                        'description' => "Permission to {$act} in {$mod} module",
                    ]
                );
            }
        }

        // 4. Global Roles
        $globalRoles = [
            'platform_admin' => 'Platform Super Admin',
            'platform_support' => 'Platform Support Admin',
            'school_owner' => 'School Owner',
            'school_admin' => 'School Admin',
            'principal' => 'Principal',
            'vice_principal' => 'Vice Principal',
            'accountant' => 'Accountant',
            'admission_officer' => 'Admission Officer',
            'teacher' => 'Teacher',
            'class_teacher' => 'Class Teacher',
            'librarian' => 'Librarian',
            'transport_manager' => 'Transport Manager',
            'hr_manager' => 'HR Manager',
            'receptionist' => 'Receptionist',
            'parent' => 'Parent',
            'student' => 'Student',
            'driver' => 'Driver',
        ];

        $roleModels = [];
        foreach ($globalRoles as $rKey => $rTitle) {
            $roleModels[$rKey] = Role::updateOrCreate(
                ['tenant_id' => null, 'name' => $rKey],
                ['display_name' => $rTitle, 'is_system_defined' => true]
            );
        }

        // Assign all permissions to school_admin role
        $roleModels['school_admin']->permissions()->sync(collect($createdPermissions)->pluck('id'));

        // 5. Standard Education Boards
        $boards = [
            ['code' => 'CBSE', 'name' => 'Central Board of Secondary Education'],
            ['code' => 'ICSE', 'name' => 'Council for the Indian School Certificate Examinations'],
            ['code' => 'STATE', 'name' => 'State Education Board'],
            ['code' => 'IB', 'name' => 'International Baccalaureate'],
            ['code' => 'CAMBRIDGE', 'name' => 'Cambridge Assessment International Education'],
        ];

        $boardModels = [];
        foreach ($boards as $b) {
            $boardModels[$b['code']] = Board::updateOrCreate(
                ['tenant_id' => null, 'code' => $b['code']],
                ['name' => $b['name'], 'is_active' => true]
            );
        }

        // 6. Platform Super Admin User
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@schoolerpsaas.com'],
            [
                'first_name' => 'Antigravity',
                'last_name' => 'Admin',
                'phone' => '+919999900000',
                'email_verified_at' => now(),
                'password' => Hash::make('SuperSecretPassword123!'),
                'is_platform_admin' => true,
                'is_active' => true,
            ]
        );

        // 7. Demo Tenant Organization
        $tenant = Tenant::updateOrCreate(
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

        // Subscription for demo tenant
        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id, 'plan_id' => $growth->id],
            [
                'billing_cycle' => 'annual',
                'status' => 'active',
                'current_period_start' => now()->startOfYear(),
                'current_period_end' => now()->endOfYear(),
            ]
        );

        // 8. Demo School & Branch
        $school = School::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'APEX-INTL'],
            [
                'name' => 'Apex International Public School',
                'affiliation_number' => 'CBSE-2130891',
                'education_board' => 'CBSE',
                'email' => 'contact@apexschool.edu',
                'phone' => '+919876543211',
                'address' => [
                    'city' => 'Greater Noida',
                    'state' => 'Uttar Pradesh',
                    'country' => 'India',
                ],
                'is_active' => true,
            ]
        );

        $branch = SchoolBranch::updateOrCreate(
            ['school_id' => $school->id, 'branch_code' => 'MAIN-01'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'North Main Campus',
                'campus_type' => 'main',
                'phone' => '+919876543212',
                'email' => 'north@apexschool.edu',
                'is_active' => true,
            ]
        );

        // School Owner User
        $owner = User::updateOrCreate(
            ['email' => 'owner@apexschool.edu'],
            [
                'first_name' => 'Rajendra',
                'last_name' => 'Verma',
                'phone' => '+919876543220',
                'email_verified_at' => now(),
                'password' => Hash::make('Password123!'),
                'is_platform_admin' => false,
                'is_active' => true,
            ]
        );

        TenantMembership::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'branch_id' => $branch->id],
            ['school_id' => $school->id, 'is_owner' => true, 'is_active' => true]
        );

        UserRole::updateOrCreate(
            ['user_id' => $owner->id, 'role_id' => $roleModels['school_owner']->id, 'tenant_id' => $tenant->id, 'branch_id' => $branch->id]
        );

        // 9. Academic Session
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

        // 10. Class Levels, Classes & Sections
        $classLevels = [
            'Primary' => ['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5'],
            'Middle' => ['Grade 6', 'Grade 7', 'Grade 8'],
            'Secondary' => ['Grade 9', 'Grade 10'],
            'Senior Secondary' => ['Grade 11', 'Grade 12'],
        ];

        $order = 1;
        $classMap = [];
        $sectionMap = [];

        foreach ($classLevels as $lvlName => $classNames) {
            $level = ClassLevel::updateOrCreate(
                ['school_id' => $school->id, 'name' => $lvlName],
                ['tenant_id' => $tenant->id, 'order_index' => $order]
            );

            foreach ($classNames as $cName) {
                $code = 'G' . filter_var($cName, FILTER_SANITIZE_NUMBER_INT);
                $cls = SchoolClass::updateOrCreate(
                    ['branch_id' => $branch->id, 'code' => $code],
                    [
                        'tenant_id' => $tenant->id,
                        'school_id' => $school->id,
                        'class_level_id' => $level->id,
                        'board_id' => $boardModels['CBSE']->id,
                        'name' => $cName,
                        'order_index' => $order++,
                        'is_active' => true,
                    ]
                );
                $classMap[$cName] = $cls;

                // Create Sections A and B for each class
                foreach (['Section A', 'Section B'] as $secName) {
                    $sec = Section::updateOrCreate(
                        ['class_id' => $cls->id, 'name' => $secName],
                        [
                            'tenant_id' => $tenant->id,
                            'branch_id' => $branch->id,
                            'room_number' => 'R-' . (100 + $cls->order_index),
                            'max_capacity' => 40,
                            'is_active' => true,
                        ]
                    );
                    $sectionMap["{$cName} {$secName}"] = $sec;
                }
            }
        }

        // 11. Core Subjects
        $subjects = [
            ['name' => 'English Language & Literature', 'code' => 'ENG-10', 'type' => 'theory'],
            ['name' => 'Mathematics', 'code' => 'MATH-10', 'type' => 'theory'],
            ['name' => 'Science (Physics, Chem, Bio)', 'code' => 'SCI-10', 'type' => 'theory'],
            ['name' => 'Social Science (History, Civics, Geo)', 'code' => 'SOC-10', 'type' => 'theory'],
            ['name' => 'Hindi Course A', 'code' => 'HIN-10', 'type' => 'theory'],
            ['name' => 'Information Technology', 'code' => 'IT-10', 'type' => 'practical'],
        ];

        foreach ($subjects as $s) {
            Subject::updateOrCreate(
                ['branch_id' => $branch->id, 'code' => $s['code']],
                [
                    'tenant_id' => $tenant->id,
                    'school_id' => $school->id,
                    'name' => $s['name'],
                    'type' => $s['type'],
                    'credit_hours' => 1.0,
                    'is_active' => true,
                ]
            );
        }

        // 12. Departments & Designations
        $academicsDept = EmployeeDepartment::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'ACAD'],
            ['tenant_id' => $tenant->id, 'name' => 'Academics Department', 'is_active' => true]
        );

        $adminDept = EmployeeDepartment::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'ADMIN'],
            ['tenant_id' => $tenant->id, 'name' => 'Administration & Accounts', 'is_active' => true]
        );

        $principalDesig = EmployeeDesignation::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'PRIN'],
            ['tenant_id' => $tenant->id, 'department_id' => $academicsDept->id, 'title' => 'Principal', 'level' => 10, 'is_active' => true]
        );

        $teacherDesig = EmployeeDesignation::updateOrCreate(
            ['branch_id' => $branch->id, 'code' => 'TGT'],
            ['tenant_id' => $tenant->id, 'department_id' => $academicsDept->id, 'title' => 'Trained Graduate Teacher (TGT)', 'level' => 5, 'is_active' => true]
        );

        // Principal Staff Member
        $principalUser = User::updateOrCreate(
            ['email' => 'principal@apexschool.edu'],
            [
                'first_name' => 'Sunil',
                'last_name' => 'Sharma',
                'phone' => '+919876543230',
                'email_verified_at' => now(),
                'password' => Hash::make('Password123!'),
                'is_active' => true,
            ]
        );

        Employee::updateOrCreate(
            ['branch_id' => $branch->id, 'employee_code' => 'EMP-001'],
            [
                'tenant_id' => $tenant->id,
                'school_id' => $school->id,
                'user_id' => $principalUser->id,
                'first_name' => 'Sunil',
                'last_name' => 'Sharma',
                'gender' => 'male',
                'date_of_birth' => '1978-05-12',
                'blood_group' => 'B+',
                'email' => 'principal@apexschool.edu',
                'phone' => '+919876543230',
                'department_id' => $academicsDept->id,
                'designation_id' => $principalDesig->id,
                'employment_type' => 'permanent',
                'date_of_joining' => '2020-06-01',
                'basic_salary' => 85000.00,
                'status' => 'active',
            ]
        );

        // 13. Sample Students, Guardians & Enrollments (Grade 10 Section A)
        $tenthA = $sectionMap['Grade 10 Section A'] ?? null;
        $classTen = $classMap['Grade 10'] ?? null;

        if ($tenthA && $classTen) {
            for ($i = 1; $i <= 5; $i++) {
                $admNo = "ADM-2026-00{$i}";
                $sUser = User::updateOrCreate(
                    ['email' => "student{$i}@apexschool.edu"],
                    [
                        'first_name' => "Student{$i}",
                        'last_name' => 'Sharma',
                        'phone' => "+91987654330{$i}",
                        'password' => Hash::make('Password123!'),
                        'is_active' => true,
                    ]
                );

                $student = Student::updateOrCreate(
                    ['branch_id' => $branch->id, 'admission_number' => $admNo],
                    [
                        'tenant_id' => $tenant->id,
                        'school_id' => $school->id,
                        'user_id' => $sUser->id,
                        'admission_date' => '2026-04-01',
                        'first_name' => "Student{$i}",
                        'last_name' => 'Sharma',
                        'gender' => $i % 2 === 0 ? 'female' : 'male',
                        'date_of_birth' => '2011-08-15',
                        'blood_group' => 'O+',
                        'nationality' => 'Indian',
                        'caste_category' => 'General',
                        'emergency_contact_name' => "Parent of Student{$i}",
                        'emergency_contact_phone' => "+91987654340{$i}",
                        'status' => 'active',
                    ]
                );

                // HISTORICAL ENROLLMENT PRESERVED
                StudentEnrollment::updateOrCreate(
                    ['student_id' => $student->id, 'academic_session_id' => $session->id],
                    [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'class_id' => $classTen->id,
                        'section_id' => $tenthA->id,
                        'roll_number' => (string) $i,
                        'enrollment_date' => '2026-04-01',
                        'status' => 'enrolled',
                    ]
                );

                // Guardian
                $guardian = Guardian::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'phone' => "+91987654340{$i}"],
                    [
                        'first_name' => "Parent{$i}",
                        'last_name' => 'Sharma',
                        'relation_type' => 'father',
                        'email' => "parent{$i}@gmail.com",
                        'occupation' => 'Business / Professional',
                        'annual_income' => 1200000.00,
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
            }
        }

        // 14. Fee Category & Structures
        $tuitionCat = FeeCategory::updateOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Tuition Fees'],
            ['tenant_id' => $tenant->id, 'description' => 'Regular academic term tuition', 'is_active' => true]
        );

        if ($classTen) {
            $feeStruct = FeeStructure::updateOrCreate(
                ['branch_id' => $branch->id, 'academic_session_id' => $session->id, 'fee_category_id' => $tuitionCat->id, 'class_id' => $classTen->id],
                [
                    'tenant_id' => $tenant->id,
                    'name' => 'Grade 10 Annual Academic Fee 2026-27',
                    'frequency' => 'annual',
                    'total_amount' => 45000.00,
                    'due_date' => '2026-07-31',
                    'is_active' => true,
                ]
            );

            FeeStructureItem::updateOrCreate(
                ['fee_structure_id' => $feeStruct->id, 'fee_head_name' => 'Tuition Fee'],
                ['tenant_id' => $tenant->id, 'amount' => 30000.00, 'is_mandatory' => true]
            );
            FeeStructureItem::updateOrCreate(
                ['fee_structure_id' => $feeStruct->id, 'fee_head_name' => 'Computer & Science Lab Fee'],
                ['tenant_id' => $tenant->id, 'amount' => 10000.00, 'is_mandatory' => true]
            );
            FeeStructureItem::updateOrCreate(
                ['fee_structure_id' => $feeStruct->id, 'fee_head_name' => 'Library & Co-Curricular Fee'],
                ['tenant_id' => $tenant->id, 'amount' => 5000.00, 'is_mandatory' => true]
            );
        }
    }
}
