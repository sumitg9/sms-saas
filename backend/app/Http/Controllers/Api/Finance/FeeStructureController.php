<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FeeCategoryRequest;
use App\Http\Requests\Finance\FeeConcessionRequest;
use App\Http\Requests\Finance\FeeStructureRequest;
use App\Services\Finance\FeeStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeStructureController extends Controller
{
    public function __construct(
        protected FeeStructureService $structureService
    ) {}

    public function listCategories(): JsonResponse
    {
        $categories = $this->structureService->listCategories();
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function storeCategory(FeeCategoryRequest $request): JsonResponse
    {
        $category = $this->structureService->createCategory($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Fee category created successfully.',
            'data' => $category,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['academic_session_id', 'class_id', 'fee_category_id', 'frequency']);
        $structures = $this->structureService->listStructures($filters);

        return response()->json([
            'success' => true,
            'data' => $structures,
        ]);
    }

    public function store(FeeStructureRequest $request): JsonResponse
    {
        $structure = $this->structureService->createStructure($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Fee structure created successfully.',
            'data' => $structure,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $structure = $this->structureService->getStructure($id);
        return response()->json([
            'success' => true,
            'data' => $structure,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $structure = $this->structureService->updateStructure($id, $request->all());
        return response()->json([
            'success' => true,
            'message' => 'Fee structure updated successfully.',
            'data' => $structure,
        ]);
    }

    public function listConcessions(): JsonResponse
    {
        $concessions = $this->structureService->listConcessions();
        return response()->json([
            'success' => true,
            'data' => $concessions,
        ]);
    }

    public function storeConcession(FeeConcessionRequest $request): JsonResponse
    {
        $concession = $this->structureService->createConcession($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Fee concession configured successfully.',
            'data' => $concession,
        ], 201);
    }
}

