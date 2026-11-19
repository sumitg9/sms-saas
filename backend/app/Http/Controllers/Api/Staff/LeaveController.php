<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\LeaveApplicationRequest;
use App\Http\Requests\Staff\LeaveReviewRequest;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Services\Staff\LeaveManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(
        protected LeaveManagementService $leaveService
    ) {}

    public function types(Request $request): JsonResponse
    {
        $types = $this->leaveService->getLeaveTypes($request->query('branch_id'));

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'annual_quota' => ['nullable', 'numeric', 'min:0'],
            'is_paid' => ['nullable', 'boolean'],
            'is_carry_forward' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = $this->leaveService->createLeaveType($validated);

        return response()->json([
            'success' => true,
            'message' => 'Leave type created successfully',
            'data' => $type,
        ], 201);
    }

    public function balances(Request $request, Employee $employee): JsonResponse
    {
        $sessionId = $request->query('academic_session_id');
        $balances = $this->leaveService->getEmployeeBalances($employee, $sessionId);

        return response()->json([
            'success' => true,
            'data' => $balances,
        ]);
    }

    public function applications(Request $request): JsonResponse
    {
        $paginator = $this->leaveService->listApplications($request->all());

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function apply(LeaveApplicationRequest $request): JsonResponse
    {
        $application = $this->leaveService->applyLeave($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Leave application submitted successfully',
            'data' => $application,
        ], 201);
    }

    public function review(LeaveReviewRequest $request, LeaveApplication $application): JsonResponse
    {
        $validated = $request->validated();
        $updated = $this->leaveService->reviewApplication(
            $application,
            $validated['status'],
            $validated['remarks'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => "Leave application {$validated['status']} successfully",
            'data' => $updated,
        ]);
    }
}

