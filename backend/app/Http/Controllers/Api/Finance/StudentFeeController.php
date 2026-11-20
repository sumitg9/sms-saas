<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StudentFeeAssignRequest;
use App\Services\Finance\StudentFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentFeeController extends Controller
{
    public function __construct(
        protected StudentFeeService $studentFeeService
    ) {}

    public function assignFee(StudentFeeAssignRequest $request): JsonResponse
    {
        $assignment = $this->studentFeeService->assignFeeStructure($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Fee assigned and invoice generated successfully.',
            'data' => $assignment,
        ], 201);
    }

    public function listInvoices(Request $request): JsonResponse
    {
        $filters = $request->only(['student_id', 'status', 'academic_session_id', 'overdue']);
        $invoices = $this->studentFeeService->listInvoices($filters);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function showInvoice(string $id): JsonResponse
    {
        $invoice = $this->studentFeeService->getInvoice($id);

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    public function applyFine(Request $request, string $invoiceId): JsonResponse
    {
        $request->validate([
            'fine_amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:150'],
            'calculation_date' => ['nullable', 'date'],
        ]);

        $fine = $this->studentFeeService->applyFine(
            $invoiceId,
            (float) $request->input('fine_amount'),
            $request->input('reason', 'Late payment penalty'),
            $request->input('calculation_date')
        );

        return response()->json([
            'success' => true,
            'message' => 'Late fine assessed successfully.',
            'data' => $fine,
        ], 201);
    }

    public function waiveFine(Request $request, string $fineId): JsonResponse
    {
        $request->validate([
            'waiver_reason' => ['required', 'string', 'max:255'],
        ]);

        $fine = $this->studentFeeService->waiveFine(
            $fineId,
            $request->input('waiver_reason'),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Late fine waived successfully.',
            'data' => $fine,
        ]);
    }

    public function studentLedger(string $studentId): JsonResponse
    {
        $ledger = $this->studentFeeService->getStudentLedger($studentId);

        return response()->json([
            'success' => true,
            'data' => $ledger,
        ]);
    }
}

