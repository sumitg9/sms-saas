<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventoryVendor;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * List categories.
     */
    public function categories(Request $request): JsonResponse
    {
        $query = InventoryCategory::withCount('items');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    /**
     * Create category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
        ]);

        $category = $this->inventoryService->createCategory($validated);

        return response()->json([
            'success' => true,
            'message' => 'Inventory category created successfully.',
            'data' => $category,
        ], 201);
    }

    /**
     * List inventory items.
     */
    public function items(Request $request): JsonResponse
    {
        $query = InventoryItem::with('category');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->boolean('low_stock_only')) {
            $query->whereColumn('current_stock', '<=', 'min_stock_alert');
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'ilike', $search)
                  ->orWhere('item_code', 'ilike', $search);
            });
        }

        $items = $query->orderBy('item_name')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Store item.
     */
    public function storeItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'category_id' => 'required|uuid|exists:inventory_categories,id',
            'item_name' => 'required|string|max:150',
            'item_code' => 'required|string|max:50',
            'unit_of_measure' => 'nullable|string|max:30',
            'min_stock_alert' => 'nullable|integer|min:0',
            'initial_stock' => 'nullable|integer|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $item = $this->inventoryService->createItem($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Inventory item cataloged successfully.',
            'data' => $item,
        ], 201);
    }

    /**
     * Show item with full stock transaction ledger.
     */
    public function showItem(string $id): JsonResponse
    {
        $item = InventoryItem::with(['category', 'transactions.creator'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    /**
     * List vendors.
     */
    public function vendors(Request $request): JsonResponse
    {
        $vendors = InventoryVendor::withCount('purchases')->orderBy('name')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $vendors->items(),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'total' => $vendors->total(),
            ],
        ]);
    }

    /**
     * Store vendor.
     */
    public function storeVendor(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'contact_person' => 'nullable|string|max:100',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'gst_number' => 'nullable|string|max:50',
        ]);

        $vendor = $this->inventoryService->createVendor($validated);

        return response()->json([
            'success' => true,
            'message' => 'Inventory vendor registered successfully.',
            'data' => $vendor,
        ], 201);
    }

    /**
     * Receive goods / purchase order intake.
     */
    public function storePurchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'vendor_id' => 'required|uuid|exists:inventory_vendors,id',
            'invoice_number' => 'required|string|max:100',
            'purchase_date' => 'nullable|date',
            'total_amount' => 'required|numeric|min:0',
            'payment_status' => 'nullable|string|in:paid,pending,partial',
            'bill_path' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|uuid|exists:inventory_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        $purchase = $this->inventoryService->receivePurchase($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Goods receipt and stock intake logged successfully.',
            'data' => $purchase,
        ], 201);
    }

    /**
     * Issue stock to department or employee.
     */
    public function issueStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|uuid|exists:inventory_items,id',
            'issued_to_type' => 'required|string|in:department,employee',
            'issued_to_id' => 'required|uuid',
            'quantity' => 'required|integer|min:1',
            'issue_date' => 'nullable|date',
            'return_date' => 'nullable|date|after_or_equal:issue_date',
            'status' => 'nullable|string|in:issued,consumed',
        ]);

        try {
            $issue = $this->inventoryService->issueStock($validated, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Stock disbursed successfully.',
                'data' => $issue,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Return issued stock back to inventory.
     */
    public function returnStock(Request $request, string $issueId): JsonResponse
    {
        $validated = $request->validate([
            'return_quantity' => 'nullable|integer|min:1',
        ]);

        try {
            $issue = $this->inventoryService->returnStock(
                $issueId,
                $validated['return_quantity'] ?? null,
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock returned to inventory successfully.',
                'data' => $issue,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Report damaged or written-off stock.
     */
    public function reportDamaged(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            $item = $this->inventoryService->reportDamagedStock(
                $id,
                $validated['quantity'],
                $validated['remarks'] ?? null,
                $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Damaged stock adjustment recorded.',
                'data' => $item,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Minimum stock alerts.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'branch_id query parameter is required.',
            ], 422);
        }

        $alerts = $this->inventoryService->getLowStockAlerts($branchId);

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Purchase and inventory reports.
     */
    public function reports(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'branch_id query parameter is required.',
            ], 422);
        }

        $reports = $this->inventoryService->getInventoryReports($branchId);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }
}

