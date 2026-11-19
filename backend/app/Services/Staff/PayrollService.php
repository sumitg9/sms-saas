<?php

namespace App\Services\Staff;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeSalaryProfile;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureItem;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    /**
     * Create a configurable salary component (allowance or deduction).
     */
    public function createSalaryComponent(array $data): SalaryComponent
    {
        $tenantId = $data['tenant_id'] ?? app(TenantContext::class)->getTenantId();
        $branchId = $data['branch_id'] ?? app(TenantContext::class)->getBranchId();

        return SalaryComponent::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'type' => $data['type'], // allowance, deduction
            'calculation_type' => $data['calculation_type'] ?? 'fixed', // fixed, percentage
            'percentage_of' => !empty($data['percentage_of']) ? strtoupper($data['percentage_of']) : null,
            'default_amount' => $data['default_amount'] ?? 0.00,
            'is_taxable' => $data['is_taxable'] ?? true,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Create a salary structure band with multiple component items.
     */
    public function createSalaryStructure(array $data): SalaryStructure
    {
        $tenantId = $data['tenant_id'] ?? app(TenantContext::class)->getTenantId();
        $branchId = $data['branch_id'] ?? app(TenantContext::class)->getBranchId();

        return DB::transaction(function () use ($data, $tenantId, $branchId) {
            $structure = SalaryStructure::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'name' => $data['name'],
                'code' => strtoupper($data['code']),
                'description' => $data['description'] ?? null,
                'base_salary' => $data['base_salary'] ?? 0.00,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    SalaryStructureItem::create([
                        'tenant_id' => $tenantId,
                        'salary_structure_id' => $structure->id,
                        'salary_component_id' => $item['salary_component_id'],
                        'amount' => $item['amount'] ?? 0.00,
                    ]);
                }
            }

            return $structure->load('items.component');
        });
    }

    /**
     * Assign salary profile to an employee.
     */
    public function assignSalaryProfile(Employee $employee, array $data): EmployeeSalaryProfile
    {
        return EmployeeSalaryProfile::updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'tenant_id' => $employee->tenant_id,
                'branch_id' => $employee->branch_id,
                'salary_structure_id' => $data['salary_structure_id'] ?? null,
                'base_salary' => $data['base_salary'] ?? $employee->basic_salary,
                'payment_method' => $data['payment_method'] ?? 'bank_transfer',
                'custom_components' => $data['custom_components'] ?? [],
                'is_active' => true,
            ]
        );
    }

    /**
     * Create a payroll period for processing.
     */
    public function createPayrollPeriod(array $data): PayrollPeriod
    {
        $tenantId = $data['tenant_id'] ?? app(TenantContext::class)->getTenantId();
        $branchId = $data['branch_id'] ?? app(TenantContext::class)->getBranchId();

        return PayrollPeriod::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'academic_session_id' => $data['academic_session_id'] ?? null,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'payment_date' => $data['payment_date'] ?? null,
            'status' => 'draft',
            'total_gross' => 0.00,
            'total_deductions' => 0.00,
            'total_net' => 0.00,
        ]);
    }

    /**
     * Generate thread-safe sequential payslip number.
     */
    public function generatePayslipNumber(string $tenantId, Carbon $periodDate): string
    {
        $year = $periodDate->year;
        $month = sprintf('%02d', $periodDate->month);
        $prefix = "PAY-{$year}-{$month}-";

        return DB::transaction(function () use ($tenantId, $prefix) {
            $latest = Payslip::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('payslip_number', 'LIKE', "{$prefix}%")
                ->lockForUpdate()
                ->orderByDesc('payslip_number')
                ->first();

            $sequence = 1;
            if ($latest && preg_match('/PAY-\d{4}-\d{2}-(\d+)/', $latest->payslip_number, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return sprintf('%s%04d', $prefix, $sequence);
        });
    }

    /**
     * Calculate payroll for all active employees within a payroll period.
     */
    public function calculatePayroll(PayrollPeriod $period, ?string $userId = null): PayrollPeriod
    {
        if ($period->status === 'paid' || $period->status === 'locked') {
            throw ValidationException::withMessages([
                'status' => 'Cannot recalculate a paid or locked payroll period.',
            ]);
        }

        return DB::transaction(function () use ($period, $userId) {
            $startDate = Carbon::parse($period->start_date);
            $endDate = Carbon::parse($period->end_date);
            $workingDays = max(1, $startDate->diffInDays($endDate) + 1);

            // Fetch active employees in this branch
            $employees = Employee::where('branch_id', $period->branch_id)
                ->where('status', 'active')
                ->with(['salaryProfile.salaryStructure.items.component'])
                ->get();

            $totalPeriodGross = 0.00;
            $totalPeriodDeductions = 0.00;
            $totalPeriodNet = 0.00;

            foreach ($employees as $employee) {
                $profile = $employee->salaryProfile;
                $structure = $profile?->salaryStructure;

                $baseSalary = (float) ($profile?->base_salary ?? $employee->basic_salary ?? 0.00);

                $earningsBreakdown = [];
                $deductionsBreakdown = [];
                $totalAllowances = 0.00;
                $totalDeductions = 0.00;

                // 1. Process Structure Items if assigned
                if ($structure && $structure->items) {
                    foreach ($structure->items as $item) {
                        $component = $item->component;
                        if (!$component || !$component->is_active) {
                            continue;
                        }

                        $componentRate = (float) ($item->amount > 0 ? $item->amount : $component->default_amount);

                        if ($component->type === 'allowance') {
                            $amount = $component->calculation_type === 'percentage'
                                ? round(($componentRate / 100.0) * $baseSalary, 2)
                                : $componentRate;

                            $earningsBreakdown[] = [
                                'component_id' => $component->id,
                                'name' => $component->name,
                                'code' => $component->code,
                                'amount' => $amount,
                                'type' => 'allowance',
                            ];
                            $totalAllowances += $amount;
                        } elseif ($component->type === 'deduction') {
                            $calcBase = ($component->percentage_of === 'GROSS')
                                ? ($baseSalary + $totalAllowances)
                                : $baseSalary;

                            $amount = $component->calculation_type === 'percentage'
                                ? round(($componentRate / 100.0) * $calcBase, 2)
                                : $componentRate;

                            $deductionsBreakdown[] = [
                                'component_id' => $component->id,
                                'name' => $component->name,
                                'code' => $component->code,
                                'amount' => $amount,
                                'type' => 'deduction',
                            ];
                            $totalDeductions += $amount;
                        }
                    }
                }

                // 2. Attendance & Unpaid Leave Pro-rata Calculation
                $unpaidLeaves = EmployeeAttendance::where('employee_id', $employee->id)
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->where('status', 'absent')
                    ->count();

                $presentDays = max(0, $workingDays - $unpaidLeaves);

                if ($unpaidLeaves > 0 && $workingDays > 0) {
                    $dailyRate = $baseSalary / $workingDays;
                    $lossOfPay = round($unpaidLeaves * $dailyRate, 2);

                    $deductionsBreakdown[] = [
                        'component_id' => null,
                        'name' => 'Loss of Pay (Unpaid Leave)',
                        'code' => 'LOP',
                        'amount' => $lossOfPay,
                        'type' => 'deduction',
                    ];
                    $totalDeductions += $lossOfPay;
                }

                $grossSalary = round($baseSalary + $totalAllowances, 2);
                $netSalary = max(0.00, round($grossSalary - $totalDeductions, 2));

                // 3. Create or Update Payslip
                $payslip = Payslip::where('payroll_period_id', $period->id)
                    ->where('employee_id', $employee->id)
                    ->first();

                $payslipNumber = $payslip?->payslip_number ?? $this->generatePayslipNumber($period->tenant_id, $startDate);

                Payslip::updateOrCreate(
                    [
                        'payroll_period_id' => $period->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'tenant_id' => $period->tenant_id,
                        'branch_id' => $period->branch_id,
                        'payslip_number' => $payslipNumber,
                        'basic_salary' => $baseSalary,
                        'total_allowances' => $totalAllowances,
                        'total_deductions' => $totalDeductions,
                        'gross_salary' => $grossSalary,
                        'net_salary' => $netSalary,
                        'working_days' => $workingDays,
                        'present_days' => $presentDays,
                        'paid_leaves' => 0.0,
                        'unpaid_leaves' => (float) $unpaidLeaves,
                        'earnings_breakdown' => $earningsBreakdown,
                        'deductions_breakdown' => $deductionsBreakdown,
                        'status' => 'draft',
                        'payment_method' => $profile?->payment_method ?? 'bank_transfer',
                    ]
                );

                $totalPeriodGross += $grossSalary;
                $totalPeriodDeductions += $totalDeductions;
                $totalPeriodNet += $netSalary;
            }

            // Update Period totals
            $period->update([
                'total_gross' => $totalPeriodGross,
                'total_deductions' => $totalPeriodDeductions,
                'total_net' => $totalPeriodNet,
                'status' => 'processing',
                'processed_by' => $userId,
            ]);

            return $period->load('payslips.employee');
        });
    }

    /**
     * Approve payroll period and publish all generated payslips.
     */
    public function approvePayrollPeriod(PayrollPeriod $period, string $userId): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $userId) {
            $period->update([
                'status' => 'approved',
                'approved_by' => $userId,
            ]);

            Payslip::where('payroll_period_id', $period->id)
                ->where('status', 'draft')
                ->update(['status' => 'published']);

            return $period;
        });
    }

    /**
     * Mark payroll period and payslips as disbursed/paid.
     */
    public function markPayrollPaid(PayrollPeriod $period, array $data = []): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $data) {
            $paymentDate = $data['payment_date'] ?? Carbon::today()->toDateString();
            $paymentMethod = $data['payment_method'] ?? 'bank_transfer';
            $paymentReference = $data['payment_reference'] ?? null;

            $period->update([
                'status' => 'paid',
                'payment_date' => $paymentDate,
            ]);

            Payslip::where('payroll_period_id', $period->id)
                ->update([
                    'status' => 'paid',
                    'payment_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentReference,
                ]);

            return $period;
        });
    }
}
