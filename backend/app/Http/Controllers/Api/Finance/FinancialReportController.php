<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    public function __construct(
        protected FinancialReportService $reportService
    ) {}

    public function daily(Request $request): JsonResponse
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $branchId = $request->input('branch_id');

        $report = $this->reportService->getDailyCollection($date, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function monthly(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', Carbon::now()->year);
        $month = (int) $request->input('month', Carbon::now()->month);
        $branchId = $request->input('branch_id');

        $report = $this->reportService->getMonthlyCollection($year, $month, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function classWise(Request $request): JsonResponse
    {
        $sessionId = $request->input('academic_session_id');
        $branchId = $request->input('branch_id');

        $report = $this->reportService->getClassWiseCollection($sessionId, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function outstanding(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');
        $classId = $request->input('class_id');

        $report = $this->reportService->getOutstandingReport($branchId, $classId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function defaulters(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');
        $minDays = (int) $request->input('min_days', 15);

        $report = $this->reportService->getDefaultersReport($branchId, $minDays);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $branchId = $request->input('branch_id');

        $report = $this->reportService->getPaymentMethodBreakdown($startDate, $endDate, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function refunds(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $branchId = $request->input('branch_id');

        $report = $this->reportService->getRefundSummary($startDate, $endDate, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $branchId = $request->input('branch_id');

        $report = $this->reportService->getFinancialSummary($startDate, $endDate, $branchId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}

