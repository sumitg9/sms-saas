<?php

namespace App\Http\Controllers\Api\Examination;

use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\PublishResultRequest;
use App\Services\Examination\ResultAnalyticsService;
use App\Services\Examination\ResultCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(
        protected ResultCalculationService $resultService,
        protected ResultAnalyticsService $analyticsService
    ) {}

    public function calculateClassResults(Request $request, string $examinationId, string $classId): JsonResponse
    {
        $results = $this->resultService->calculateClassResults($examinationId, $classId);

        return response()->json([
            'success' => true,
            'message' => 'Class examination results and rankings calculated successfully.',
            'data' => $results,
        ]);
    }

    public function publishResults(PublishResultRequest $request, string $examinationId): JsonResponse
    {
        $publication = $this->resultService->publishResults(
            $examinationId,
            $request->input('class_id'),
            $request->user(),
            $request->only(['allow_parent_view', 'allow_student_view'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Class examination results published successfully.',
            'data' => $publication,
        ]);
    }

    public function classPerformance(string $examinationId, string $classId): JsonResponse
    {
        $summary = $this->analyticsService->getClassPerformanceSummary($examinationId, $classId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function toppers(Request $request, string $examinationId, string $classId): JsonResponse
    {
        $limit = (int) $request->input('limit', 3);
        $toppers = $this->analyticsService->getToppersReport($examinationId, $classId, $limit);

        return response()->json([
            'success' => true,
            'data' => $toppers,
        ]);
    }

    public function subjectWise(string $examinationId, string $classId): JsonResponse
    {
        $metrics = $this->analyticsService->getSubjectWisePerformance($examinationId, $classId);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    public function studentHistory(string $studentId): JsonResponse
    {
        $history = $this->analyticsService->getStudentHistoricalComparison($studentId);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }
}

