<?php

namespace App\Services\Staff;

use App\Models\AcademicSession;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\EmployeeExit;
use App\Models\EmployeeLeave;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeService
{
    /**
     * Generate thread-safe sequential employee code.
     */
    public function generateEmployeeCode(string $tenantId, string $branchId): string
    {
        $year = Carbon::now()->year;
        $prefix = "EMP-{$year}-";

        return DB::transaction(function () use ($tenantId, $branchId, $prefix) {
            $latest = Employee::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branchId)
                ->where('employee_code', 'LIKE', "{$prefix}%")
                ->lockForUpdate()
                ->orderByDesc('employee_code')
                ->first();

            $sequence = 1;
            if ($latest && preg_match('/EMP-\d{4}-(\d+)/', $latest->employee_code, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return sprintf('%s%04d', $prefix, $sequence);
        });
    }

    /**
     * Register a new employee with optional contract, user account, and leave quotas.
     */
    public function registerEmployee(array $data): Employee
    {
        $tenantId = $data['tenant_id'] ?? app(TenantContext::class)->getTenantId();
        $branchId = $data['branch_id'] ?? app(TenantContext::class)->getBranchId();

        return DB::transaction(function () use ($data, $tenantId, $branchId) {
            // Generate code if missing
            $employeeCode = $data['employee_code'] ?? $this->generateEmployeeCode($tenantId, $branchId);

            // Optional User Account creation for Employee portal access
            $userId = $data['user_id'] ?? null;
            if (!$userId && !empty($data['create_user_account'])) {
                $user = User::create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => bcrypt($data['password'] ?? Str::random(12)),
                    'is_active' => true,
                ]);
                $role = $data['user_role'] ?? 'teacher';
                $user->assignRole($role, $tenantId);
                $userId = $user->id;
            }

            // Create Employee
            $employee = Employee::create([
                'tenant_id' => $tenantId,
                'school_id' => $data['school_id'],
                'branch_id' => $branchId,
                'user_id' => $userId,
                'employee_code' => $employeeCode,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'gender' => $data['gender'],
                'date_of_birth' => $data['date_of_birth'],
                'blood_group' => $data['blood_group'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'],
                'department_id' => $data['department_id'],
                'designation_id' => $data['designation_id'],
                'employment_type' => $data['employment_type'] ?? 'permanent',
                'date_of_joining' => $data['date_of_joining'] ?? Carbon::today(),
                'basic_salary' => $data['basic_salary'] ?? 0.00,
                'pan_number' => $data['pan_number'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_ifsc_code' => $data['bank_ifsc_code'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'status' => 'active',
            ]);

            // Create Initial Contract if contract details provided
            if (isset($data['gross_salary']) || isset($data['contract_type'])) {
                EmployeeContract::create([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'contract_type' => $data['contract_type'] ?? $employee->employment_type,
                    'start_date' => $employee->date_of_joining,
                    'end_date' => $data['contract_end_date'] ?? null,
                    'gross_salary' => $data['gross_salary'] ?? $employee->basic_salary,
                    'terms' => $data['contract_terms'] ?? ['working_hours' => 40],
                    'is_active' => true,
                ]);
            }

            // Initialize Leave Quotas for current active academic session
            $currentSession = AcademicSession::where('branch_id', $branchId)
                ->where('is_current', true)
                ->first();

            if ($currentSession) {
                $leaveTypes = LeaveType::where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->get();

                foreach ($leaveTypes as $type) {
                    EmployeeLeave::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'employee_id' => $employee->id,
                            'academic_session_id' => $currentSession->id,
                            'leave_type' => $type->code,
                        ],
                        [
                            'total_days' => $type->annual_quota,
                            'used_days' => 0.0,
                            'balance_days' => $type->annual_quota,
                        ]
                    );
                }
            }

            return $employee->load(['department', 'designation']);
        });
    }

    /**
     * Update employee demographic, contact, and employment information.
     */
    public function updateEmployee(Employee $employee, array $data): Employee
    {
        $employee->update(array_filter([
            'first_name' => $data['first_name'] ?? $employee->first_name,
            'middle_name' => array_key_exists('middle_name', $data) ? $data['middle_name'] : $employee->middle_name,
            'last_name' => $data['last_name'] ?? $employee->last_name,
            'gender' => $data['gender'] ?? $employee->gender,
            'date_of_birth' => $data['date_of_birth'] ?? $employee->date_of_birth,
            'blood_group' => array_key_exists('blood_group', $data) ? $data['blood_group'] : $employee->blood_group,
            'email' => $data['email'] ?? $employee->email,
            'phone' => $data['phone'] ?? $employee->phone,
            'department_id' => $data['department_id'] ?? $employee->department_id,
            'designation_id' => $data['designation_id'] ?? $employee->designation_id,
            'employment_type' => $data['employment_type'] ?? $employee->employment_type,
            'basic_salary' => $data['basic_salary'] ?? $employee->basic_salary,
            'pan_number' => array_key_exists('pan_number', $data) ? $data['pan_number'] : $employee->pan_number,
            'bank_account_number' => array_key_exists('bank_account_number', $data) ? $data['bank_account_number'] : $employee->bank_account_number,
            'bank_ifsc_code' => array_key_exists('bank_ifsc_code', $data) ? $data['bank_ifsc_code'] : $employee->bank_ifsc_code,
            'bank_name' => array_key_exists('bank_name', $data) ? $data['bank_name'] : $employee->bank_name,
            'status' => $data['status'] ?? $employee->status,
        ], fn ($val) => $val !== null));

        return $employee->load(['department', 'designation']);
    }

    /**
     * Attach a verified credential or document.
     */
    public function addDocument(Employee $employee, array $data): EmployeeDocument
    {
        return EmployeeDocument::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'document_type' => $data['document_type'],
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'verification_status' => $data['verification_status'] ?? 'verified',
        ]);
    }

    /**
     * Create or renew an employment contract.
     */
    public function addContract(Employee $employee, array $data): EmployeeContract
    {
        return DB::transaction(function () use ($employee, $data) {
            // Deactivate previous contracts if active
            EmployeeContract::where('employee_id', $employee->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return EmployeeContract::create([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'contract_type' => $data['contract_type'] ?? 'permanent',
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'gross_salary' => $data['gross_salary'],
                'terms' => $data['terms'] ?? [],
                'is_active' => true,
            ]);
        });
    }

    /**
     * Process employee resignation, termination, or retirement.
     */
    public function recordExit(Employee $employee, array $data, ?string $approverId = null): EmployeeExit
    {
        return DB::transaction(function () use ($employee, $data, $approverId) {
            $exitType = $data['exit_type'] ?? 'resignation';
            $lastWorkingDay = $data['last_working_day'] ?? Carbon::today();

            // Update employee status and leaving date
            $employee->update([
                'status' => in_array($exitType, ['resignation', 'termination', 'retirement']) ? $exitType : 'resigned',
                'date_of_leaving' => $lastWorkingDay,
            ]);

            // If user account is linked, deactivate it
            if ($employee->user) {
                $employee->user->update(['is_active' => false]);
            }

            // Create exit record
            return EmployeeExit::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'tenant_id' => $employee->tenant_id,
                    'branch_id' => $employee->branch_id,
                    'exit_type' => $exitType,
                    'notice_date' => $data['notice_date'] ?? Carbon::today(),
                    'last_working_day' => $lastWorkingDay,
                    'reason' => $data['reason'],
                    'clearance_status' => $data['clearance_status'] ?? 'pending',
                    'settlement_status' => $data['settlement_status'] ?? 'pending',
                    'clearance_checklist' => $data['clearance_checklist'] ?? [
                        'it_clearance' => false,
                        'library_clearance' => false,
                        'finance_clearance' => false,
                        'admin_clearance' => false,
                    ],
                    'approved_by' => $approverId,
                    'remarks' => $data['remarks'] ?? null,
                ]
            );
        });
    }

    /**
     * Get 360-degree employee dossier.
     */
    public function getEmployee360(Employee $employee): array
    {
        $employee->load([
            'department',
            'designation',
            'contracts' => fn ($q) => $q->orderByDesc('start_date'),
            'documents',
            'leaves',
            'salaryProfile.salaryStructure',
            'exitRecord',
        ]);

        return [
            'employee' => $employee,
            'active_contract' => $employee->contracts->firstWhere('is_active', true),
            'leave_balances' => $employee->leaves,
            'documents_count' => $employee->documents->count(),
            'is_exited' => !is_null($employee->exitRecord),
        ];
    }
}
