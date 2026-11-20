<?php

namespace App\Http\Controllers\Api\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\DashboardAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardAnalyticsService $analyticsService
    ) {}

    /**
     * Role-routed dashboard overview for the authenticated user.
     */
    public function userDashboard(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');
        $dashboard = $this->analyticsService->getDashboardForUser($request->user(), $branchId);

        return response()->json([
            'success' => true,
            'role' => $dashboard['role'],
            'data' => $dashboard['data'],
        ]);
    }

    /**
     * Platform Super Admin Analytics.
     */
    public function platformAnalytics(Request $request): JsonResponse
    {
        if (!$request->user()->is_platform_admin && !$request->user()->hasRole('super_admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to platform super admin analytics.',
            ], 403);
        }

        $analytics = $this->analyticsService->getPlatformAnalytics();

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * School Admin & Principal Analytics.
     */
    public function schoolAnalytics(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id') ?? $request->user()->memberships()->first()?->branch_id;
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'branch_id query parameter is required.',
            ], 422);
        }

        $analytics = $this->analyticsService->getSchoolAdminDashboard($branchId);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Accountant Financial Analytics.
     */
    public function financialAnalytics(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id') ?? $request->user()->memberships()->first()?->branch_id;
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'branch_id query parameter is required.',
            ], 422);
        }

        $analytics = $this->analyticsService->getAccountantDashboard($branchId);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }
}

