<?php

namespace App\Http\Controllers\Api\Lms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\QuizAttemptSubmitRequest;
use App\Http\Requests\Lms\QuizRequest;
use App\Services\Lms\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function __construct(
        protected QuizService $quizService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['class_id', 'subject_id', 'is_published']);
        $quizzes = $this->quizService->listQuizzes($filters);

        return response()->json([
            'success' => true,
            'data' => $quizzes,
        ]);
    }

    public function store(QuizRequest $request): JsonResponse
    {
        $quiz = $this->quizService->createQuiz($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Online quiz created successfully.',
            'data' => $quiz,
        ], 201);
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        $publish = $request->boolean('is_published', true);
        $quiz = $this->quizService->publishQuiz($id, $publish);

        return response()->json([
            'success' => true,
            'message' => $publish ? 'Quiz published to students.' : 'Quiz unpublished.',
            'data' => $quiz,
        ]);
    }

    public function startAttempt(Request $request, string $id): JsonResponse
    {
        $attempt = $this->quizService->startQuizAttempt(
            $id,
            $request->user(),
            $request->input('student_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz attempt initialized.',
            'data' => $attempt,
        ], 201);
    }

    public function submitAttempt(QuizAttemptSubmitRequest $request, string $attemptId): JsonResponse
    {
        $attempt = $this->quizService->submitQuizAttempt(
            $attemptId,
            $request->input('answers')
        );

        return response()->json([
            'success' => true,
            'message' => 'Quiz submitted and auto-graded successfully.',
            'data' => $attempt,
        ]);
    }

    public function report(string $id): JsonResponse
    {
        $report = $this->quizService->getQuizReport($id);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}

