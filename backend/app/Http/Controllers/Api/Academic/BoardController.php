<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    public function __construct(protected TenantContext $context) {}

    /**
     * List all education boards applicable to the tenant.
     */
    public function index(): JsonResponse
    {
        $tenantId = $this->context->getTenantId();

        $boards = Board::whereNull('tenant_id')
            ->orWhere('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $boards,
        ]);
    }

    /**
     * Create a custom board for the tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
        ]);

        $tenantId = $this->context->getTenantId();
        $code = strtoupper(trim($validated['code']));

        $exists = Board::where('code', $code)
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "Board with code '{$code}' already exists.",
            ], 422);
        }

        $board = Board::create([
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Board configured successfully.',
            'data' => $board,
        ], 201);
    }
}

