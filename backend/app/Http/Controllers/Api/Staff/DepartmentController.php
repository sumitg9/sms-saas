<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDepartment;
use App\Models\EmployeeDesignation;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = app(TenantContext::class)->getBranchId();

        $departments = EmployeeDepartment::where('branch_id', $branchId)
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $department = EmployeeDepartment::create([
            'tenant_id' => app(TenantContext::class)->getTenantId(),
            'branch_id' => app(TenantContext::class)->getBranchId(),
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'data' => $department,
        ], 201);
    }

    public function designations(Request $request): JsonResponse
    {
        $branchId = app(TenantContext::class)->getBranchId();

        $query = EmployeeDesignation::where('branch_id', $branchId)
            ->with('department')
            ->withCount('employees')
            ->orderBy('title');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->query('department_id'));
        }

        $designations = $query->get();

        return response()->json([
            'success' => true,
            'data' => $designations,
        ]);
    }

    public function storeDesignation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'level' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $designation = EmployeeDesignation::create([
            'tenant_id' => app(TenantContext::class)->getTenantId(),
            'branch_id' => app(TenantContext::class)->getBranchId(),
            'department_id' => $validated['department_id'] ?? null,
            'title' => $validated['title'],
            'code' => strtoupper($validated['code']),
            'level' => $validated['level'] ?? 1,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Designation created successfully',
            'data' => $designation,
        ], 201);
    }
}
