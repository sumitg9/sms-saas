<?php

namespace App\Http\Controllers\Api\Lms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\HomeworkRequest;
use App\Services\Lms\HomeworkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeworkController extends Controller
{
    public function __construct(
        protected HomeworkService $homeworkService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['class_id', 'section_id', 'subject_id', 'teacher_id', 'is_active']);
        $homework = $this->homeworkService->listHomework($filters);

        return response()->json([
            'success' => true,
            'data' => $homework,
        ]);
    }

    public function store(HomeworkRequest $request): JsonResponse
    {
        $homework = $this->homeworkService->createHomework($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Homework assigned successfully.',
            'data' => $homework,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $homework = $this->homeworkService->getHomework($id);

        return response()->json([
            'success' => true,
            'data' => $homework,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $homework = $this->homeworkService->updateHomework($id, $request->all(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Homework updated successfully.',
            'data' => $homework,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->homeworkService->deleteHomework($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Homework deleted successfully.',
        ]);
    }
}

