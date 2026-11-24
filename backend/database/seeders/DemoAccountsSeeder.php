<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Models\Guardian;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentGuardian;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'apex-education-trust')->first();
        if (!$tenant) {
            return;
        }

        $school = School::where('tenant_id', $tenant->id)->first();
        $branch = SchoolBranch::where('tenant_id', $tenant->id)->first();

        $roleModels = Role::whereNull('tenant_id')->get()->keyBy('name');

        // 1. Platform Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@schoolerpsaas.com'],
            [
                'first_name' => 'Antigravity',
                'last_name' => 'Admin',
                'phone' => '+919999900000',
                'email_verified_at' => now(),
                'password' => Hash::make('Password123!'),
                'is_platform_admin' => true,
                'is_active' => true,
            ]
        );

        // 2. School Owner
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
            ['tenant_id' => $tenant->id, 'user_id' => $owner->id],
            ['school_id' => $school->id, 'branch_id' => $branch->id, 'is_owner' => true, 'is_active' => true]
        );
        if (isset($roleModels['school_owner'])) {
            UserRole::updateOrCreate(
                ['user_id' => $owner->id, 'tenant_id' => $tenant->id],
                ['role_id' => $roleModels['school_owner']->id, 'branch_id' => $branch->id]
            );
        }

        // 3. School Admin
        $admin = User::updateOrCreate(
            ['email' => 'admin@apexschool.edu'],
            [
                'first_name' => 'Vikram',
                'last_name' => 'Aditya',
                'phone' => '+919876543221',
                'email_verified_at' => now(),
                'password' => Hash::make('Password123!'),
                'is_platform_admin' => false,
                'is_active' => true,
            ]
        );
        TenantMembership::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $admin->id],
            ['school_id' => $school->id, 'branch_id' => $branch->id, 'is_owner' => false, 'is_active' => true]
        );
        if (isset($roleModels['school_admin'])) {
            UserRole::updateOrCreate(
                ['user_id' => $admin->id, 'tenant_id' => $tenant->id],
                ['role_id' => $roleModels['school_admin']->id, 'branch_id' => $branch->id]
            );
        }

        // 4. Principal
        $principal = User::updateOrCreate(
            ['email' => 'principal@apexschool.edu'],
            [
                'first_name' => 'Sunil',
                'last_name' => 'Sharma',
                'phone' => '+919876543230',
                'email_verified_at' => now(),
                'password' => Hash::make('Password123!'),
                'is_platform_admin' => false,
                'is_active' => true,
            ]
        );
        TenantMembership::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $principal->id],
            ['school_id' => $school->id, 'branch_id' => $branch->id, 'is_owner' => false, 'is_active' => true]
        );
        if (isset($roleModels['principal'])) {
            UserRole::updateOrCreate(
                ['user_id' => $principal->id, 'tenant_id' => $tenant->id],
                ['role_id' => $roleModels['principal']->id, 'branch_id' => $branch->id]
            );
        }

        // 5. Teacher
        $teacher = User::updateOrCreate(
            ['email' => 'teacher@apexschool.edu'],
            [
                'first_name' => 'Ananya',
                'last_name' => 'Iyer',
                'phone' => '+919876543240',
                'email_verified_at' => now(),
                'password' => Hash::make('Password123!'),
                'is_platform_admin' => false,
                'is_active' => true,
            ]
        );
        TenantMembership::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $teacher->id],
            ['school_id' => $school->id, 'branch_id' => $branch->id, 'is_owner' => false, 'is_active' => true]
        );
        if (isset($roleModels['teacher'])) {
            UserRole::updateOrCreate(
                ['user_id' => $teacher->id, 'tenant_id' => $tenant->id],
                ['role_id' => $roleModels['teacher']->id, 'branch_id' => $branch->id]
            );
        }

        // 6. Accountant
        $accountant = User::updateOrCreate(
            ['email' => 'accountant@apexschool.edu'],
            [
                'first_name' => 'Ramesh',
                'last_name' => 'Gupta',
                'phone' => '+919876543250',
                'email_verified_at' => now(),
                'password' => Hash::make('Password123!'),
                'is_platform_admin' => false,
                'is_active' => true,
            ]
        );
        TenantMembership::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $accountant->id],
            ['school_id' => $school->id, 'branch_id' => $branch->id, 'is_owner' => false, 'is_active' => true]
        );
        if (isset($roleModels['accountant'])) {
            UserRole::updateOrCreate(
                ['user_id' => $accountant->id, 'tenant_id' => $tenant->id],
                ['role_id' => $roleModels['accountant']->id, 'branch_id' => $branch->id]
            );
        }

        // 7. Student
        $studentUser = User::updateOrCreate(
            ['email' => 'student@apexschool.edu'],
            [
                'first_name' => 'Aarav',
                'last_name' => 'Sharma',
                'phone' => '+919876543260',
                'email_verified_at' => now(),
                'password' => Hash::make('Password123!'),
                'is_platform_admin' => false,
                'is_active' => true,
            ]
        );
        TenantMembership::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $studentUser->id],
            ['school_id' => $school->id, 'branch_id' => $branch->id, 'is_owner' => false, 'is_active' => true]
        );
        if (isset($roleModels['student'])) {
            UserRole::updateOrCreate(
                ['user_id' => $studentUser->id, 'tenant_id' => $tenant->id],
                ['role_id' => $roleModels['student']->id, 'branch_id' => $branch->id]
            );
        }

        // 8. Parent
        $parentUser = User::updateOrCreate(
            ['email' => 'parent@apexschool.edu'],
            [
                'first_name' => 'Sanjay',
                'last_name' => 'Sharma',
                'phone' => '+919876543270',
                'email_verified_at' => now(),
                'password' => Hash::make('Password123!'),
                'is_platform_admin' => false,
                'is_active' => true,
            ]
        );
        TenantMembership::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $parentUser->id],
            ['school_id' => $school->id, 'branch_id' => $branch->id, 'is_owner' => false, 'is_active' => true]
        );
        if (isset($roleModels['parent'])) {
            UserRole::updateOrCreate(
                ['user_id' => $parentUser->id, 'tenant_id' => $tenant->id],
                ['role_id' => $roleModels['parent']->id, 'branch_id' => $branch->id]
            );
        }
    }
}

