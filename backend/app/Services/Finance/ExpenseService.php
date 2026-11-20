<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * List expense categories.
     */
    public function listCategories(): Collection
    {
        return ExpenseCategory::query()
            ->when($this->tenantContext->getBranchId(), function ($query, $branchId) {
                $query->where('branch_id', $branchId);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Create expense category.
     */
    public function createCategory(array $data): ExpenseCategory
    {
        $branchId = $data['branch_id'] ?? $this->tenantContext->getBranchId();

        return ExpenseCategory::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $branchId,
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * List expenses with filters.
     */
    public function listExpenses(array $filters = []): Collection
    {
        return Expense::query()
            ->with(['category', 'account', 'approver'])
            ->when($this->tenantContext->getBranchId(), function ($query, $branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when(isset($filters['expense_category_id']), function ($query) use ($filters) {
                $query->where('expense_category_id', $filters['expense_category_id']);
            })
            ->when(isset($filters['start_date']), function ($query) use ($filters) {
                $query->whereDate('expense_date', '>=', $filters['start_date']);
            })
            ->when(isset($filters['end_date']), function ($query) use ($filters) {
                $query->whereDate('expense_date', '<=', $filters['end_date']);
            })
            ->when(isset($filters['financial_account_id']), function ($query) use ($filters) {
                $query->where('financial_account_id', $filters['financial_account_id']);
            })
            ->orderBy('expense_date', 'desc')
            ->get();
    }

    /**
     * Record an expense with sequential voucher numbering and account balance update.
     */
    public function recordExpense(array $data, ?string $userId = null): Expense
    {
        return DB::transaction(function () use ($data, $userId) {
            $tenantId = $this->tenantContext->getTenantId();
            $branchId = $data['branch_id'] ?? $this->tenantContext->getBranchId();
            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw new InvalidArgumentException('Expense amount must be greater than zero.');
            }

            $voucherNumber = $this->generateVoucherNumber($tenantId);

            $expense = Expense::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'expense_category_id' => $data['expense_category_id'],
                'financial_account_id' => $data['financial_account_id'] ?? null,
                'voucher_number' => $voucherNumber,
                'title' => $data['title'],
                'amount' => $amount,
                'expense_date' => $data['expense_date'] ?? Carbon::today(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'vendor_name' => $data['vendor_name'] ?? null,
                'bill_path' => $data['bill_path'] ?? null,
                'approved_by' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            // Deduct from financial account if specified
            if (!empty($data['financial_account_id'])) {
                $account = FinancialAccount::findOrFail($data['financial_account_id']);
                $account->update([
                    'current_balance' => round((float) $account->current_balance - $amount, 2),
                ]);
            }

            return $expense->load(['category', 'account', 'approver']);
        });
    }

    /**
     * List financial accounts.
     */
    public function listAccounts(): Collection
    {
        return FinancialAccount::query()
            ->when($this->tenantContext->getBranchId(), function ($query, $branchId) {
                $query->where('branch_id', $branchId);
            })
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get();
    }

    /**
     * Create financial account.
     */
    public function createAccount(array $data): FinancialAccount
    {
        $branchId = $data['branch_id'] ?? $this->tenantContext->getBranchId();
        $openingBalance = round((float) ($data['opening_balance'] ?? 0.00), 2);

        return FinancialAccount::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $branchId,
            'account_name' => $data['account_name'],
            'account_number' => $data['account_number'],
            'bank_name' => $data['bank_name'],
            'ifsc_code' => $data['ifsc_code'] ?? null,
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Generate sequential voucher number VCH-YYYY-XXXX.
     */
    protected function generateVoucherNumber(string $tenantId): string
    {
        $year = Carbon::now()->year;
        $count = Expense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('VCH-%d-%05d', $year, $count + 1);
    }
}

