<?php

namespace App\Http\Controllers\Api\Examination;

use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\ExaminationRequest;
use App\Http\Requests\Examination\ExaminationTypeRequest;
use App\Http\Requests\Examination\ExamScheduleRequest;
use App\Services\Examination\ExaminationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExaminationController extends Controller
{
    public function __construct(
        protected ExaminationService $examinationService
    ) {}

    public function listTypes(Request $request): JsonResponse
    {
        $types = $this->examinationService->listTypes($request->input('school_id'));

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    public function storeType(ExaminationTypeRequest $request): JsonResponse
    {
        $type = $this->examinationService->createType($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Examination type created successfully.',
            'data' => $type,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['academic_session_id', 'exam_type_id', 'is_published']);
        $examinations = $this->examinationService->listExaminations($filters);

        return response()->json([
            'success' => true,
            'data' => $examinations,
        ]);
    }

    public function store(ExaminationRequest $request): JsonResponse
    {
        $examination = $this->examinationService->createExamination($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Examination session created successfully.',
            'data' => $examination,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $examination = $this->examinationService->getExamination($id);

        return response()->json([
            'success' => true,
            'data' => $examination,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $examination = $this->examinationService->updateExamination($id, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Examination updated successfully.',
            'data' => $examination,
        ]);
    }

    public function schedule(ExamScheduleRequest $request): JsonResponse
    {
        $schedule = $this->examinationService->scheduleExam($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Subject exam scheduled successfully.',
            'data' => $schedule,
        ], 201);
    }

    public function listSchedules(Request $request, string $examinationId): JsonResponse
    {
        $classId = $request->input('class_id');
        $schedules = $this->examinationService->listSchedules($examinationId, $classId);

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    public function getSchedule(string $scheduleId): JsonResponse
    {
        $schedule = $this->examinationService->getSchedule($scheduleId);

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }
}

