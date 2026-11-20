<?php

namespace App\Services\Finance;

use App\Models\FeeCategory;
use App\Models\FeeConcession;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FeeStructureService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * List all fee categories for the current tenant branch.
     */
    public function listCategories(): Collection
    {
        return FeeCategory::query()
            ->when($this->tenantContext->getBranchId(), function ($query, $branchId) {
                $query->where('branch_id', $branchId);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a fee category.
     */
    public function createCategory(array $data): FeeCategory
    {
        $branchId = $data['branch_id'] ?? $this->tenantContext->getBranchId();

        return FeeCategory::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $branchId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * List fee structures with items and relationships.
     */
    public function listStructures(array $filters = []): Collection
    {
        return FeeStructure::query()
            ->with(['category', 'academicSession', 'schoolClass', 'items'])
            ->when($this->tenantContext->getBranchId(), function ($query, $branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when(isset($filters['academic_session_id']), function ($query) use ($filters) {
                $query->where('academic_session_id', $filters['academic_session_id']);
            })
            ->when(isset($filters['class_id']), function ($query) use ($filters) {
                $query->where('class_id', $filters['class_id']);
            })
            ->when(isset($filters['fee_category_id']), function ($query) use ($filters) {
                $query->where('fee_category_id', $filters['fee_category_id']);
            })
            ->when(isset($filters['frequency']), function ($query) use ($filters) {
                $query->where('frequency', $filters['frequency']);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get a single fee structure with items.
     */
    public function getStructure(string $id): FeeStructure
    {
        return FeeStructure::with(['category', 'academicSession', 'schoolClass', 'items'])->findOrFail($id);
    }

    /**
     * Create a fee structure with line items in a transaction.
     */
    public function createStructure(array $data): FeeStructure
    {
        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'] ?? $this->tenantContext->getBranchId();
            $tenantId = $this->tenantContext->getTenantId();

            $items = $data['items'] ?? [];
            $totalAmount = 0.00;

            foreach ($items as $item) {
                $totalAmount += (float) ($item['amount'] ?? 0);
            }

            $structure = FeeStructure::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'academic_session_id' => $data['academic_session_id'],
                'fee_category_id' => $data['fee_category_id'],
                'class_id' => $data['class_id'] ?? null,
                'name' => $data['name'],
                'frequency' => $data['frequency'] ?? 'annual',
                'total_amount' => round($totalAmount, 2),
                'due_date' => $data['due_date'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($items as $item) {
                FeeStructureItem::create([
                    'tenant_id' => $tenantId,
                    'fee_structure_id' => $structure->id,
                    'fee_head_name' => $item['fee_head_name'],
                    'amount' => round((float) $item['amount'], 2),
                    'is_mandatory' => $item['is_mandatory'] ?? true,
                ]);
            }

            return $structure->load(['category', 'academicSession', 'schoolClass', 'items']);
        });
    }

    /**
     * Update a fee structure.
     */
    public function updateStructure(string $id, array $data): FeeStructure
    {
        return DB::transaction(function () use ($id, $data) {
            $structure = FeeStructure::findOrFail($id);
            $tenantId = $this->tenantContext->getTenantId();

            if (isset($data['items'])) {
                // Delete existing items and recreate
                $structure->items()->delete();
                $totalAmount = 0.00;

                foreach ($data['items'] as $item) {
                    $totalAmount += (float) ($item['amount'] ?? 0);
                    FeeStructureItem::create([
                        'tenant_id' => $tenantId,
                        'fee_structure_id' => $structure->id,
                        'fee_head_name' => $item['fee_head_name'],
                        'amount' => round((float) $item['amount'], 2),
                        'is_mandatory' => $item['is_mandatory'] ?? true,
                    ]);
                }
                $data['total_amount'] = round($totalAmount, 2);
                unset($data['items']);
            }

            $structure->update($data);

            return $structure->fresh(['category', 'academicSession', 'schoolClass', 'items']);
        });
    }

    /**
     * List all concessions.
     */
    public function listConcessions(): Collection
    {
        return FeeConcession::query()
            ->when($this->tenantContext->getBranchId(), function ($query, $branchId) {
                $query->where('branch_id', $branchId);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a fee concession.
     */
    public function createConcession(array $data): FeeConcession
    {
        $branchId = $data['branch_id'] ?? $this->tenantContext->getBranchId();

        return FeeConcession::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $branchId,
            'name' => $data['name'],
            'discount_type' => $data['discount_type'] ?? 'percentage',
            'discount_value' => round((float) $data['discount_value'], 2),
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}

