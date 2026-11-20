<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\TeacherPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherPortalController extends Controller
{
    public function __construct(
        protected TeacherPortalService $teacherPortalService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->teacherPortalService->getDashboard(
            $request->user(),
            $request->input('employee_id')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function classes(Request $request): JsonResponse
    {
        $data = $this->teacherPortalService->getAssignedClassesAndSubjects(
            $request->user(),
            $request->input('employee_id')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function timetable(Request $request): JsonResponse
    {
        $data = $this->teacherPortalService->getTeacherTimetable(
            $request->user(),
            $request->input('employee_id')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function studentPerformance(Request $request, string $classId, string $subjectId): JsonResponse
    {
        $data = $this->teacherPortalService->getStudentPerformance(
            $request->user(),
            $classId,
            $subjectId,
            $request->input('employee_id')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}

