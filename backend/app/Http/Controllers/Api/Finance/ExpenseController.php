<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ExpenseRequest;
use App\Http\Requests\Finance\FinancialAccountRequest;
use App\Services\Finance\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    public function listCategories(): JsonResponse
    {
        $categories = $this->expenseService->listCategories();
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $category = $this->expenseService->createCategory($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Expense category created successfully.',
            'data' => $category,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['expense_category_id', 'financial_account_id', 'start_date', 'end_date']);
        $expenses = $this->expenseService->listExpenses($filters);

        return response()->json([
            'success' => true,
            'data' => $expenses,
        ]);
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenseService->recordExpense(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Expense voucher recorded successfully.',
            'data' => $expense,
        ], 201);
    }

    public function listAccounts(): JsonResponse
    {
        $accounts = $this->expenseService->listAccounts();
        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function storeAccount(FinancialAccountRequest $request): JsonResponse
    {
        $account = $this->expenseService->createAccount($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Financial account configured successfully.',
            'data' => $account,
        ], 201);
    }
}

