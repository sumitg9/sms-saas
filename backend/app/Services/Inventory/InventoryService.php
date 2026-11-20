<?php

namespace App\Services\Inventory;

use App\Models\Employee;
use App\Models\EmployeeDepartment;
use App\Models\InventoryCategory;
use App\Models\InventoryIssue;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\InventoryStockTransaction;
use App\Models\InventoryVendor;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Create category.
     */
    public function createCategory(array $data): InventoryCategory
    {
        return InventoryCategory::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'code' => strtoupper(trim($data['code'])),
        ]);
    }

    /**
     * Create inventory item.
     */
    public function createItem(array $data, ?string $userId = null): InventoryItem
    {
        return DB::transaction(function () use ($data, $userId) {
            $initialStock = isset($data['initial_stock']) ? (int) $data['initial_stock'] : 0;

            $item = InventoryItem::create([
                'branch_id' => $data['branch_id'],
                'category_id' => $data['category_id'],
                'item_name' => $data['item_name'],
                'item_code' => strtoupper(trim($data['item_code'])),
                'unit_of_measure' => $data['unit_of_measure'] ?? 'pcs',
                'min_stock_alert' => $data['min_stock_alert'] ?? 5,
                'current_stock' => $initialStock,
            ]);

            if ($initialStock > 0) {
                InventoryStockTransaction::create([
                    'branch_id' => $item->branch_id,
                    'item_id' => $item->id,
                    'transaction_type' => 'adjustment',
                    'quantity' => $initialStock,
                    'balance_after' => $initialStock,
                    'unit_cost' => $data['unit_cost'] ?? 0.00,
                    'remarks' => 'Initial stock on item creation',
                    'created_by' => $userId,
                ]);
            }

            return $item->fresh(['category']);
        });
    }

    /**
     * Register vendor.
     */
    public function createVendor(array $data): InventoryVendor
    {
        return InventoryVendor::create([
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'gst_number' => $data['gst_number'] ?? null,
        ]);
    }

    /**
     * Goods receipt / purchase order intake.
     */
    public function receivePurchase(array $data, ?string $userId = null): InventoryPurchase
    {
        return DB::transaction(function () use ($data, $userId) {
            $vendor = InventoryVendor::findOrFail($data['vendor_id']);

            $purchase = InventoryPurchase::create([
                'branch_id' => $data['branch_id'],
                'vendor_id' => $vendor->id,
                'invoice_number' => strtoupper(trim($data['invoice_number'])),
                'purchase_date' => $data['purchase_date'] ?? Carbon::today()->toDateString(),
                'total_amount' => $data['total_amount'],
                'payment_status' => $data['payment_status'] ?? 'paid',
                'bill_path' => $data['bill_path'] ?? null,
            ]);

            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $entry) {
                    $item = InventoryItem::where('branch_id', $purchase->branch_id)->findOrFail($entry['item_id']);
                    $qty = (int) $entry['quantity'];
                    $unitCost = (float) ($entry['unit_cost'] ?? 0.00);

                    $newStock = $item->current_stock + $qty;
                    $item->update(['current_stock' => $newStock]);

                    InventoryStockTransaction::create([
                        'branch_id' => $purchase->branch_id,
                        'item_id' => $item->id,
                        'transaction_type' => 'purchase',
                        'quantity' => $qty,
                        'balance_after' => $newStock,
                        'unit_cost' => $unitCost,
                        'reference_type' => 'purchase',
                        'reference_id' => $purchase->id,
                        'remarks' => "Purchased from {$vendor->name} (Invoice: {$purchase->invoice_number})",
                        'created_by' => $userId,
                    ]);
                }
            }

            return $purchase->fresh(['vendor', 'transactions.item']);
        });
    }

    /**
     * Issue/disburse inventory item to department or employee.
     */
    public function issueStock(array $data, ?string $userId = null): InventoryIssue
    {
        return DB::transaction(function () use ($data, $userId) {
            $item = InventoryItem::findOrFail($data['item_id']);
            $qty = (int) $data['quantity'];

            if ($item->current_stock < $qty) {
                throw new InvalidArgumentException("Insufficient stock for '{$item->item_name}'. Available: {$item->current_stock}, Requested: {$qty}.");
            }

            // Validate target exists
            if ($data['issued_to_type'] === 'department') {
                EmployeeDepartment::withoutGlobalScopes()->findOrFail($data['issued_to_id']);
            } elseif ($data['issued_to_type'] === 'employee') {
                Employee::withoutGlobalScopes()->findOrFail($data['issued_to_id']);
            } else {
                throw new InvalidArgumentException("Invalid target type '{$data['issued_to_type']}'. Must be 'department' or 'employee'.");
            }

            $issue = InventoryIssue::create([
                'item_id' => $item->id,
                'issued_to_type' => $data['issued_to_type'],
                'issued_to_id' => $data['issued_to_id'],
                'quantity' => $qty,
                'issue_date' => $data['issue_date'] ?? Carbon::today()->toDateString(),
                'return_date' => $data['return_date'] ?? null,
                'status' => $data['status'] ?? 'issued',
            ]);

            $newStock = $item->current_stock - $qty;
            $item->update(['current_stock' => $newStock]);

            InventoryStockTransaction::create([
                'branch_id' => $item->branch_id,
                'item_id' => $item->id,
                'transaction_type' => 'issue',
                'quantity' => -$qty,
                'balance_after' => $newStock,
                'reference_type' => 'issue',
                'reference_id' => $issue->id,
                'remarks' => "Disbursed to {$data['issued_to_type']}",
                'created_by' => $userId,
            ]);

            return $issue->fresh(['item']);
        });
    }

    /**
     * Return issued stock back to inventory.
     */
    public function returnStock(string $issueId, ?int $returnQuantity = null, ?string $userId = null): InventoryIssue
    {
        return DB::transaction(function () use ($issueId, $returnQuantity, $userId) {
            $issue = InventoryIssue::with('item')->findOrFail($issueId);

            if ($issue->status === 'returned') {
                throw new InvalidArgumentException("This issue has already been returned.");
            }

            $qtyToReturn = $returnQuantity ?? $issue->quantity;
            if ($qtyToReturn > $issue->quantity) {
                throw new InvalidArgumentException("Return quantity cannot exceed issued quantity ({$issue->quantity}).");
            }

            $item = $issue->item;
            $newStock = $item->current_stock + $qtyToReturn;
            $item->update(['current_stock' => $newStock]);

            $issue->update([
                'status' => 'returned',
                'return_date' => Carbon::today()->toDateString(),
            ]);

            InventoryStockTransaction::create([
                'branch_id' => $item->branch_id,
                'item_id' => $item->id,
                'transaction_type' => 'return',
                'quantity' => $qtyToReturn,
                'balance_after' => $newStock,
                'reference_type' => 'issue_return',
                'reference_id' => $issue->id,
                'remarks' => 'Returned back to stock',
                'created_by' => $userId,
            ]);

            return $issue->fresh(['item']);
        });
    }

    /**
     * Record damaged or written-off stock.
     */
    public function reportDamagedStock(string $itemId, int $quantity, ?string $remarks = null, ?string $userId = null): InventoryItem
    {
        return DB::transaction(function () use ($itemId, $quantity, $remarks, $userId) {
            $item = InventoryItem::findOrFail($itemId);

            if ($quantity <= 0) {
                throw new InvalidArgumentException("Damaged quantity must be greater than zero.");
            }

            if ($item->current_stock < $quantity) {
                throw new InvalidArgumentException("Damaged quantity ({$quantity}) exceeds current available stock ({$item->current_stock}).");
            }

            $newStock = $item->current_stock - $quantity;
            $item->update(['current_stock' => $newStock]);

            InventoryStockTransaction::create([
                'branch_id' => $item->branch_id,
                'item_id' => $item->id,
                'transaction_type' => 'damaged',
                'quantity' => -$quantity,
                'balance_after' => $newStock,
                'remarks' => $remarks ?? 'Damaged or written-off items',
                'created_by' => $userId,
            ]);

            return $item->fresh();
        });
    }

    /**
     * Get items that breached the minimum stock alert threshold.
     */
    public function getLowStockAlerts(string $branchId): Collection
    {
        return InventoryItem::where('branch_id', $branchId)
            ->whereColumn('current_stock', '<=', 'min_stock_alert')
            ->with('category')
            ->orderBy('current_stock', 'asc')
            ->get();
    }

    /**
     * Get reports on inventory, purchases, and disbursements.
     */
    public function getInventoryReports(string $branchId): array
    {
        $items = InventoryItem::where('branch_id', $branchId)->get();
        $totalItems = $items->count();
        $totalStockUnits = $items->sum('current_stock');

        $lowStockItems = $this->getLowStockAlerts($branchId);

        $purchases = InventoryPurchase::where('branch_id', $branchId)->with('vendor')->get();
        $totalPurchaseSpend = $purchases->sum('total_amount');

        $totalIssuesCount = InventoryIssue::whereHas('item', fn($q) => $q->where('branch_id', $branchId))->count();

        $categoryBreakdown = InventoryCategory::where('branch_id', $branchId)
            ->withCount('items')
            ->get();

        return [
            'total_items_cataloged' => $totalItems,
            'total_stock_units' => (int) $totalStockUnits,
            'low_stock_alerts_count' => $lowStockItems->count(),
            'low_stock_items' => $lowStockItems,
            'total_purchases_count' => $purchases->count(),
            'total_purchase_spend' => (float) $totalPurchaseSpend,
            'total_disbursements_count' => $totalIssuesCount,
            'categories' => $categoryBreakdown,
        ];
    }
}

