<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\StudentPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentPortalController extends Controller
{
    public function __construct(
        protected StudentPortalService $studentPortalService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->studentPortalService->getDashboard(
            $request->user(),
            $request->input('student_id')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function timetable(Request $request): JsonResponse
    {
        $data = $this->studentPortalService->getTimetable(
            $request->user(),
            $request->input('student_id')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function homework(Request $request): JsonResponse
    {
        $data = $this->studentPortalService->getHomeworkList(
            $request->user(),
            $request->input('student_id')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function learningResources(Request $request): JsonResponse
    {
        $data = $this->studentPortalService->getLearningResources(
            $request->user(),
            $request->input('student_id')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function reportCards(Request $request): JsonResponse
    {
        $data = $this->studentPortalService->getReportCards(
            $request->user(),
            $request->input('student_id')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}

