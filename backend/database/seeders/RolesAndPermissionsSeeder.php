<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define Granular Permissions Matrix
        $modules = [
            'students' => ['view', 'create', 'edit', 'delete', 'export', 'print'],
            'admissions' => ['view', 'create', 'edit', 'delete', 'approve', 'reject', 'export'],
            'academics' => ['view', 'create', 'edit', 'delete', 'manage'],
            'attendance' => ['view', 'create', 'edit', 'manage', 'export'],
            'fees' => ['view', 'create', 'edit', 'delete', 'collect', 'refund', 'approve', 'export', 'print'],
            'examinations' => ['view', 'create', 'edit', 'delete', 'publish', 'export', 'print'],
            'staff' => ['view', 'create', 'edit', 'delete', 'manage', 'export'],
            'payroll' => ['view', 'create', 'edit', 'delete', 'manage', 'export'],
            'library' => ['view', 'create', 'edit', 'delete', 'manage', 'export'],
            'transport' => ['view', 'create', 'edit', 'delete', 'manage', 'export'],
            'inventory' => ['view', 'create', 'edit', 'delete', 'manage', 'export'],
            'hostel' => ['view', 'create', 'edit', 'delete', 'manage', 'export'],
            'communication' => ['view', 'create', 'edit', 'delete', 'manage'],
            'reports' => ['view', 'export', 'print'],
            'subscriptions' => ['view', 'manage'],
            'homework' => ['view', 'create', 'edit', 'delete', 'submit', 'grade'],
            'lms' => ['view', 'create', 'edit', 'delete', 'manage'],
        ];

        $permissionModels = [];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $permissionModels[$name] = Permission::updateOrCreate(
                    ['name' => $name],
                    [
                        'guard_name' => 'web',
                        'module' => $module,
                        'display_name' => ucfirst($action) . ' ' . ucfirst($module),
                        'description' => "Allows user to {$action} in {$module} domain",
                    ]
                );
            }
        }

        // 2. Define System Roles
        $roles = [
            // Platform Roles
            'platform_admin' => [
                'display' => 'Platform Super Admin',
                'description' => 'Full unconstrained platform control over all tenants and system infrastructure.',
                'permissions' => array_keys($permissionModels), // All permissions
            ],
            'platform_support' => [
                'display' => 'Platform Support Admin',
                'description' => 'Read-only diagnostic and customer support access.',
                'permissions' => ['students.view', 'academics.view', 'reports.view', 'subscriptions.view'],
            ],

            // School Roles
            'school_owner' => [
                'display' => 'School Owner',
                'description' => 'Complete institutional authority, billing, subscription, and operations.',
                'permissions' => array_keys($permissionModels),
            ],
            'school_admin' => [
                'display' => 'School Admin',
                'description' => 'Operations manager across all academic, financial, and student modules.',
                'permissions' => array_diff(array_keys($permissionModels), ['subscriptions.manage']),
            ],
            'principal' => [
                'display' => 'Principal',
                'description' => 'Academic head, admissions approval, exam publications, and staff oversight.',
                'permissions' => [
                    'students.view', 'students.edit', 'students.export', 'students.print',
                    'admissions.view', 'admissions.approve', 'admissions.reject',
                    'academics.view', 'academics.manage',
                    'attendance.view', 'attendance.manage',
                    'fees.view', 'fees.approve',
                    'examinations.view', 'examinations.publish', 'examinations.export', 'examinations.print',
                    'staff.view', 'staff.manage',
                    'reports.view', 'reports.export', 'reports.print',
                ],
            ],
            'vice_principal' => [
                'display' => 'Vice Principal',
                'description' => 'Supports academic operations, timetable, and student disciplinary matters.',
                'permissions' => [
                    'students.view', 'students.edit',
                    'academics.view', 'academics.manage',
                    'attendance.view', 'attendance.manage',
                    'examinations.view', 'examinations.print',
                    'staff.view',
                    'reports.view',
                ],
            ],
            'accountant' => [
                'display' => 'Accountant',
                'description' => 'Manages fee structures, ledger collections, invoices, and refunds.',
                'permissions' => [
                    'fees.view', 'fees.create', 'fees.edit', 'fees.collect', 'fees.refund', 'fees.export', 'fees.print',
                    'payroll.view', 'payroll.create', 'payroll.edit', 'payroll.manage', 'payroll.export',
                    'students.view',
                    'reports.view', 'reports.export', 'reports.print',
                ],
            ],
            'admission_officer' => [
                'display' => 'Admission Officer',
                'description' => 'Inquiry pipeline, student onboarding, applicant verification.',
                'permissions' => [
                    'admissions.view', 'admissions.create', 'admissions.edit', 'admissions.approve', 'admissions.reject',
                    'students.view', 'students.create', 'students.edit',
                ],
            ],
            'teacher' => [
                'display' => 'Teacher',
                'description' => 'Class attendance, homework, exam marks grading, and timetable.',
                'permissions' => [
                    'students.view',
                    'academics.view',
                    'attendance.view', 'attendance.create', 'attendance.edit',
                    'examinations.view', 'examinations.create', 'examinations.edit',
                    'homework.view', 'homework.create', 'homework.edit', 'homework.delete', 'homework.grade',
                    'lms.view', 'lms.create', 'lms.edit', 'lms.delete', 'lms.manage',
                ],
            ],
            'class_teacher' => [
                'display' => 'Class Teacher',
                'description' => 'Class section in-charge, attendance approvals, and report card generation.',
                'permissions' => [
                    'students.view', 'students.edit',
                    'academics.view',
                    'attendance.view', 'attendance.create', 'attendance.edit', 'attendance.manage',
                    'examinations.view', 'examinations.create', 'examinations.edit', 'examinations.print',
                    'homework.view', 'homework.create', 'homework.edit', 'homework.delete', 'homework.grade',
                    'lms.view', 'lms.create', 'lms.edit', 'lms.delete', 'lms.manage',
                    'reports.view', 'reports.print',
                ],
            ],
            'librarian' => [
                'display' => 'Librarian',
                'description' => 'Library books catalogue, circulation, reservations, and overdue fines.',
                'permissions' => [
                    'library.view', 'library.create', 'library.edit', 'library.delete', 'library.manage', 'library.export',
                    'students.view',
                ],
            ],
            'transport_manager' => [
                'display' => 'Transport Manager',
                'description' => 'Vehicle fleet, driver logs, routes, stops, and student bus tracking.',
                'permissions' => [
                    'transport.view', 'transport.create', 'transport.edit', 'transport.delete', 'transport.manage', 'transport.export',
                    'students.view',
                ],
            ],
            'hr_manager' => [
                'display' => 'HR Manager',
                'description' => 'Staff records, contracts, leaves, designations, and attendance.',
                'permissions' => [
                    'staff.view', 'staff.create', 'staff.edit', 'staff.delete', 'staff.manage', 'staff.export',
                    'payroll.view', 'payroll.create', 'payroll.edit', 'payroll.manage', 'payroll.export',
                    'reports.view', 'reports.export',
                ],
            ],
            'receptionist' => [
                'display' => 'Receptionist',
                'description' => 'Front desk visitor logs, gate passes, and parent inquiry coordination.',
                'permissions' => [
                    'students.view',
                    'admissions.view', 'admissions.create',
                    'communication.view', 'communication.create',
                ],
            ],
            'parent' => [
                'display' => 'Parent',
                'description' => 'Access children profiles, report cards, attendance, and pay online fees.',
                'permissions' => [
                    'students.view',
                    'attendance.view',
                    'fees.view',
                    'examinations.view',
                    'homework.view',
                    'lms.view',
                ],
            ],
            'student' => [
                'display' => 'Student',
                'description' => 'Access own timetable, homework assignments, attendance, and grades.',
                'permissions' => [
                    'students.view',
                    'attendance.view',
                    'academics.view',
                    'examinations.view',
                    'homework.view',
                    'homework.submit',
                    'lms.view',
                ],
            ],
            'driver' => [
                'display' => 'Driver',
                'description' => 'Access assigned bus routes, pickup stops, and student passenger rosters.',
                'permissions' => [
                    'transport.view',
                    'students.view',
                ],
            ],
        ];

        foreach ($roles as $roleKey => $roleData) {
            $role = Role::updateOrCreate(
                ['tenant_id' => null, 'name' => $roleKey],
                [
                    'guard_name' => 'web',
                    'display_name' => $roleData['display'],
                    'description' => $roleData['description'],
                    'is_system_defined' => true,
                ]
            );

            // Sync assigned permissions
            $permissionIds = collect($roleData['permissions'])
                ->map(fn ($p) => $permissionModels[$p]->id ?? null)
                ->filter()
                ->values();

            $role->permissions()->sync($permissionIds);
        }
    }
}

