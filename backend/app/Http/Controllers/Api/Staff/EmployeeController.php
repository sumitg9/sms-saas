<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\EmployeeContractRequest;
use App\Http\Requests\Staff\EmployeeExitRequest;
use App\Http\Requests\Staff\EmployeeRegisterRequest;
use App\Models\Employee;
use App\Services\Staff\EmployeeService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $branchId = app(TenantContext::class)->getBranchId();

        $query = Employee::where('branch_id', $branchId)
            ->with(['department', 'designation'])
            ->orderBy('first_name');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }

        if ($request->filled('designation_id')) {
            $query->where('designation_id', $request->query('designation_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->query('employment_type'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ILIKE', $search)
                    ->orWhere('last_name', 'ILIKE', $search)
                    ->orWhere('employee_code', 'ILIKE', $search)
                    ->orWhere('email', 'ILIKE', $search)
                    ->orWhere('phone', 'ILIKE', $search);
            });
        }

        $employees = $query->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $employees->items(),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    public function store(EmployeeRegisterRequest $request): JsonResponse
    {
        $employee = $this->employeeService->registerEmployee($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee registered successfully',
            'data' => $employee,
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        $dossier = $this->employeeService->getEmployee360($employee);

        return response()->json([
            'success' => true,
            'data' => $dossier,
        ]);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'gender' => ['sometimes', 'in:male,female,other'],
            'date_of_birth' => ['sometimes', 'date'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'department_id' => ['sometimes', 'uuid'],
            'designation_id' => ['sometimes', 'uuid'],
            'employment_type' => ['sometimes', 'in:permanent,contract,temporary,part_time'],
            'basic_salary' => ['sometimes', 'numeric', 'min:0'],
            'pan_number' => ['nullable', 'string', 'max:50'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_ifsc_code' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:active,on_leave,resigned,terminated'],
        ]);

        $updated = $this->employeeService->updateEmployee($employee, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'data' => $updated,
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully',
        ]);
    }

    public function addContract(EmployeeContractRequest $request, Employee $employee): JsonResponse
    {
        $contract = $this->employeeService->addContract($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee contract recorded successfully',
            'data' => $contract,
        ], 201);
    }

    public function addDocument(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'file_name' => ['required', 'string', 'max:255'],
            'file_path' => ['required', 'string', 'max:500'],
            'verification_status' => ['nullable', 'string', 'in:verified,pending,rejected'],
        ]);

        $document = $this->employeeService->addDocument($employee, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => $document,
        ], 201);
    }

    public function recordExit(EmployeeExitRequest $request, Employee $employee): JsonResponse
    {
        $exit = $this->employeeService->recordExit($employee, $request->validated(), $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Employee exit processed successfully',
            'data' => $exit,
        ]);
    }
}
