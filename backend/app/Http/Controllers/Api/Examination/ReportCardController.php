<?php

namespace App\Http\Controllers\Api\Examination;

use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\ReportCardTemplateRequest;
use App\Services\Examination\ReportCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportCardController extends Controller
{
    public function __construct(
        protected ReportCardService $reportCardService
    ) {}

    public function listTemplates(Request $request): JsonResponse
    {
        $templates = $this->reportCardService->listTemplates($request->input('school_id'));

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function storeTemplate(ReportCardTemplateRequest $request): JsonResponse
    {
        $template = $this->reportCardService->createTemplate($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Report card template created successfully.',
            'data' => $template,
        ], 201);
    }

    public function generateStudentReportCard(Request $request, string $examinationId, string $studentId): JsonResponse
    {
        $options = [
            'show_ranking' => $request->boolean('show_ranking', true),
            'teacher_remarks' => $request->input('teacher_remarks'),
            'principal_remarks' => $request->input('principal_remarks'),
            'is_signed' => $request->boolean('is_signed', false),
        ];

        $reportCard = $this->reportCardService->generateStudentReportCard(
            $examinationId,
            $studentId,
            $request->input('template_id'),
            $options
        );

        return response()->json([
            'success' => true,
            'data' => $reportCard,
        ]);
    }
}

