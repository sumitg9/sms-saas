<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PayrollPeriodRequest;
use App\Http\Requests\Staff\SalaryComponentRequest;
use App\Http\Requests\Staff\SalaryStructureRequest;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Services\Staff\PayrollService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {}

    public function components(Request $request): JsonResponse
    {
        $branchId = app(TenantContext::class)->getBranchId();

        $query = SalaryComponent::where('branch_id', $branchId)->orderBy('name');
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    public function storeComponent(SalaryComponentRequest $request): JsonResponse
    {
        $component = $this->payrollService->createSalaryComponent($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Salary component created successfully',
            'data' => $component,
        ], 201);
    }

    public function structures(Request $request): JsonResponse
    {
        $branchId = app(TenantContext::class)->getBranchId();

        $structures = SalaryStructure::where('branch_id', $branchId)
            ->with(['items.component'])
            ->withCount('employeeProfiles')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $structures,
        ]);
    }

    public function storeStructure(SalaryStructureRequest $request): JsonResponse
    {
        $structure = $this->payrollService->createSalaryStructure($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Salary structure created successfully',
            'data' => $structure,
        ], 201);
    }

    public function assignProfile(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'salary_structure_id' => ['nullable', 'uuid'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:bank_transfer,cash,cheque'],
            'custom_components' => ['nullable', 'array'],
        ]);

        $profile = $this->payrollService->assignSalaryProfile($employee, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee salary profile updated successfully',
            'data' => $profile,
        ]);
    }

    public function periods(Request $request): JsonResponse
    {
        $branchId = app(TenantContext::class)->getBranchId();

        $periods = PayrollPeriod::where('branch_id', $branchId)
            ->with(['processedByUser', 'approvedByUser'])
            ->withCount('payslips')
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $periods,
        ]);
    }

    public function storePeriod(PayrollPeriodRequest $request): JsonResponse
    {
        $period = $this->payrollService->createPayrollPeriod($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payroll period created successfully',
            'data' => $period,
        ], 201);
    }

    public function calculate(Request $request, PayrollPeriod $period): JsonResponse
    {
        $result = $this->payrollService->calculatePayroll($period, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Payroll calculated successfully',
            'data' => $result,
        ]);
    }

    public function approve(Request $request, PayrollPeriod $period): JsonResponse
    {
        $result = $this->payrollService->approvePayrollPeriod($period, $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Payroll period approved and payslips published',
            'data' => $result,
        ]);
    }

    public function pay(Request $request, PayrollPeriod $period): JsonResponse
    {
        $validated = $request->validate([
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'in:bank_transfer,cash,cheque'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $this->payrollService->markPayrollPaid($period, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Payroll marked as paid and disbursed',
            'data' => $result,
        ]);
    }

    public function payslips(Request $request): JsonResponse
    {
        $branchId = app(TenantContext::class)->getBranchId();

        $query = Payslip::where('branch_id', $branchId)
            ->with(['employee.department', 'employee.designation', 'payrollPeriod'])
            ->orderByDesc('created_at');

        if ($request->filled('payroll_period_id')) {
            $query->where('payroll_period_id', $request->query('payroll_period_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->query('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $payslips = $query->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $payslips->items(),
            'meta' => [
                'current_page' => $payslips->currentPage(),
                'last_page' => $payslips->lastPage(),
                'total' => $payslips->total(),
            ],
        ]);
    }

    public function showPayslip(Payslip $payslip): JsonResponse
    {
        $payslip->load(['employee.department', 'employee.designation', 'payrollPeriod']);

        return response()->json([
            'success' => true,
            'data' => $payslip,
        ]);
    }
}
