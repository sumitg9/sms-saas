<?php

namespace App\Http\Controllers\Api\Lms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\GradeSubmissionRequest;
use App\Http\Requests\Lms\HomeworkSubmissionRequest;
use App\Services\Lms\AssignmentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentSubmissionController extends Controller
{
    public function __construct(
        protected AssignmentSubmissionService $submissionService
    ) {}

    public function submit(HomeworkSubmissionRequest $request, string $homeworkId): JsonResponse
    {
        $submission = $this->submissionService->submitAssignment($homeworkId, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Assignment submitted successfully.',
            'data' => $submission,
        ], 201);
    }

    public function grade(GradeSubmissionRequest $request, string $submissionId): JsonResponse
    {
        $submission = $this->submissionService->gradeSubmission($submissionId, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Assignment submission evaluated and graded.',
            'data' => $submission,
        ]);
    }

    public function requestResubmission(Request $request, string $submissionId): JsonResponse
    {
        $request->validate(['feedback' => 'required|string']);

        $submission = $this->submissionService->requestResubmission(
            $submissionId,
            $request->input('feedback'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Resubmission requested from student.',
            'data' => $submission,
        ]);
    }

    public function index(string $homeworkId): JsonResponse
    {
        $submissions = $this->submissionService->listSubmissions($homeworkId);

        return response()->json([
            'success' => true,
            'data' => $submissions,
        ]);
    }

    public function show(string $submissionId): JsonResponse
    {
        $submission = $this->submissionService->getSubmission($submissionId);

        return response()->json([
            'success' => true,
            'data' => $submission,
        ]);
    }
}

