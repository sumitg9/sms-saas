<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\ParentPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentPortalController extends Controller
{
    public function __construct(
        protected ParentPortalService $parentPortalService
    ) {}

    public function children(Request $request): JsonResponse
    {
        $children = $this->parentPortalService->getLinkedChildren($request->user());

        return response()->json([
            'success' => true,
            'data' => $children,
        ]);
    }

    public function childDashboard(Request $request, string $studentId): JsonResponse
    {
        $data = $this->parentPortalService->getChildDashboard($request->user(), $studentId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function childFees(Request $request, string $studentId): JsonResponse
    {
        $data = $this->parentPortalService->getChildFees($request->user(), $studentId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function childTimetable(Request $request, string $studentId): JsonResponse
    {
        $data = $this->parentPortalService->getChildTimetable($request->user(), $studentId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function submitChildLeave(Request $request, string $studentId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'leave_type' => 'nullable|string',
            'medical_certificate_path' => 'nullable|string',
        ]);

        $leave = $this->parentPortalService->submitChildLeave(
            $request->user(),
            $studentId,
            $request->all()
        );

        return response()->json([
            'success' => true,
            'message' => 'Leave application submitted for school review.',
            'data' => $leave,
        ], 201);
    }

    public function communicate(Request $request, string $studentId): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:200',
            'message' => 'required|string',
            'category' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
        ]);

        $ticket = $this->parentPortalService->sendCommunication(
            $request->user(),
            $studentId,
            $request->all()
        );

        return response()->json([
            'success' => true,
            'message' => 'Message delivered to teacher and school administration.',
            'data' => $ticket,
        ], 201);
    }
}

