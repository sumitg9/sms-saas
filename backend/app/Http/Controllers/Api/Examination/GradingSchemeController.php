<?php

namespace App\Http\Controllers\Api\Examination;

use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\GradingSchemeRequest;
use App\Services\Examination\GradingSchemeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradingSchemeController extends Controller
{
    public function __construct(
        protected GradingSchemeService $gradingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $schemes = $this->gradingService->listSchemes($request->input('school_id'));

        return response()->json([
            'success' => true,
            'data' => $schemes,
        ]);
    }

    public function store(GradingSchemeRequest $request): JsonResponse
    {
        $scheme = $this->gradingService->createScheme($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Grading scheme created successfully.',
            'data' => $scheme,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $scheme = $this->gradingService->getScheme($id);

        return response()->json([
            'success' => true,
            'data' => $scheme,
        ]);
    }
}

