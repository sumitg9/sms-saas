<?php

namespace App\Http\Controllers\Api\Lms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lms\LearningResourceRequest;
use App\Services\Lms\LearningResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningResourceController extends Controller
{
    public function __construct(
        protected LearningResourceService $resourceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['class_id', 'section_id', 'subject_id', 'resource_type', 'is_published']);
        $resources = $this->resourceService->listResources($filters);

        return response()->json([
            'success' => true,
            'data' => $resources,
        ]);
    }

    public function store(LearningResourceRequest $request): JsonResponse
    {
        $resource = $this->resourceService->createResource($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Study material uploaded successfully.',
            'data' => $resource,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $resource = $this->resourceService->updateResource($id, $request->all(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Learning resource updated successfully.',
            'data' => $resource,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->resourceService->deleteResource($id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Learning resource removed.',
        ]);
    }
}

