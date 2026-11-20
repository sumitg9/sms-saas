<?php

namespace App\Services\Finance;

use App\Models\FeeConcession;
use App\Models\FeeFine;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceItem;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFeeAssignment;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentFeeService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Assign a fee structure to a student and automatically generate an invoice.
     */
    public function assignFeeStructure(array $data): StudentFeeAssignment
    {
        return DB::transaction(function () use ($data) {
            $tenantId = $this->tenantContext->getTenantId();
            $branchId = $data['branch_id'] ?? $this->tenantContext->getBranchId();

            $structure = FeeStructure::with('items')->findOrFail($data['fee_structure_id']);
            $enrollment = StudentEnrollment::findOrFail($data['enrollment_id']);
            $studentId = $data['student_id'] ?? $enrollment->student_id;

            $grossAmount = (float) $structure->total_amount;
            $discountAmount = 0.00;

            if (!empty($data['concession_id'])) {
                $concession = FeeConcession::findOrFail($data['concession_id']);
                if ($concession->discount_type === 'percentage') {
                    $discountAmount = round($grossAmount * ((float) $concession->discount_value / 100), 2);
                } else {
                    $discountAmount = min($grossAmount, (float) $concession->discount_value);
                }
            }

            $netAmount = max(0.00, round($grossAmount - $discountAmount, 2));

            // Create or update student fee assignment
            $assignment = StudentFeeAssignment::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'fee_structure_id' => $structure->id,
                ],
                [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'student_id' => $studentId,
                    'concession_id' => $data['concession_id'] ?? null,
                    'gross_amount' => $grossAmount,
                    'discount_amount' => $discountAmount,
                    'net_amount' => $netAmount,
                    'status' => 'active',
                ]
            );

            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber($tenantId);

            // Create the invoice
            $invoice = FeeInvoice::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'student_id' => $studentId,
                'enrollment_id' => $enrollment->id,
                'fee_assignment_id' => $assignment->id,
                'academic_session_id' => $structure->academic_session_id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $grossAmount,
                'discount_amount' => $discountAmount,
                'fine_amount' => 0.00,
                'total_amount' => $netAmount,
                'paid_amount' => 0.00,
                'balance_amount' => $netAmount,
                'due_date' => $structure->due_date ?? Carbon::now()->addDays(30),
                'status' => $netAmount == 0.00 ? 'paid' : 'unpaid',
            ]);

            // Create invoice line items matching fee structure items
            foreach ($structure->items as $item) {
                FeeInvoiceItem::create([
                    'tenant_id' => $tenantId,
                    'fee_invoice_id' => $invoice->id,
                    'fee_head_name' => $item->fee_head_name,
                    'amount' => $item->amount,
                    'paid_amount' => 0.00,
                ]);
            }

            return $assignment->load(['student', 'enrollment', 'structure', 'concession', 'invoices.items']);
        });
    }

    /**
     * List fee invoices with filters.
     */
    public function listInvoices(array $filters = []): Collection
    {
        return FeeInvoice::query()
            ->with(['student.user', 'items', 'academicSession'])
            ->when($this->tenantContext->getBranchId(), function ($query, $branchId) {
                $query->where('branch_id', $branchId);
            })
            ->when(isset($filters['student_id']), function ($query) use ($filters) {
                $query->where('student_id', $filters['student_id']);
            })
            ->when(isset($filters['status']), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when(isset($filters['academic_session_id']), function ($query) use ($filters) {
                $query->where('academic_session_id', $filters['academic_session_id']);
            })
            ->when(!empty($filters['overdue']), function ($query) {
                $query->where('due_date', '<', Carbon::today())
                    ->whereIn('status', ['unpaid', 'partially_paid', 'overdue']);
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get a single invoice with full details.
     */
    public function getInvoice(string $id): FeeInvoice
    {
        return FeeInvoice::with([
            'student.user',
            'enrollment.schoolClass',
            'enrollment.section',
            'academicSession',
            'items',
            'allocations.payment',
            'fines',
        ])->findOrFail($id);
    }

    /**
     * Apply a late fee fine to an invoice.
     */
    public function applyFine(string $invoiceId, float $fineAmount, string $reason = 'Late payment penalty', ?string $calculationDate = null): FeeFine
    {
        return DB::transaction(function () use ($invoiceId, $fineAmount, $reason, $calculationDate) {
            $invoice = FeeInvoice::findOrFail($invoiceId);
            $tenantId = $this->tenantContext->getTenantId();

            $fine = FeeFine::create([
                'tenant_id' => $tenantId,
                'fee_invoice_id' => $invoice->id,
                'fine_amount' => round($fineAmount, 2),
                'reason' => $reason,
                'calculation_date' => $calculationDate ?? Carbon::today(),
                'is_waived' => false,
            ]);

            $newFineAmount = round((float) $invoice->fine_amount + $fineAmount, 2);
            $newTotalAmount = round((float) $invoice->total_amount + $fineAmount, 2);
            $newBalanceAmount = round((float) $invoice->balance_amount + $fineAmount, 2);

            $status = $invoice->status;
            if ($newBalanceAmount > 0) {
                $status = ($invoice->paid_amount > 0) ? 'partially_paid' : 'overdue';
            }

            $invoice->update([
                'fine_amount' => $newFineAmount,
                'total_amount' => $newTotalAmount,
                'balance_amount' => $newBalanceAmount,
                'status' => $status,
            ]);

            return $fine;
        });
    }

    /**
     * Waive a fee fine.
     */
    public function waiveFine(string $fineId, string $waiverReason, ?string $userId = null): FeeFine
    {
        return DB::transaction(function () use ($fineId, $waiverReason, $userId) {
            $fine = FeeFine::findOrFail($fineId);

            if ($fine->is_waived) {
                return $fine;
            }

            $invoice = FeeInvoice::findOrFail($fine->fee_invoice_id);

            $fine->update([
                'is_waived' => true,
                'waived_by' => $userId,
                'waiver_reason' => $waiverReason,
            ]);

            $fineAmount = (float) $fine->fine_amount;
            $newFineAmount = max(0.00, round((float) $invoice->fine_amount - $fineAmount, 2));
            $newTotalAmount = max(0.00, round((float) $invoice->total_amount - $fineAmount, 2));
            $newBalanceAmount = max(0.00, round((float) $invoice->balance_amount - $fineAmount, 2));

            $status = $invoice->status;
            if ($newBalanceAmount <= 0.00) {
                $status = 'paid';
            } elseif ($invoice->paid_amount > 0) {
                $status = 'partially_paid';
            } else {
                $status = ($invoice->due_date < Carbon::today()) ? 'overdue' : 'unpaid';
            }

            $invoice->update([
                'fine_amount' => $newFineAmount,
                'total_amount' => $newTotalAmount,
                'balance_amount' => $newBalanceAmount,
                'status' => $status,
            ]);

            return $fine;
        });
    }

    /**
     * Compile complete student fee ledger.
     */
    public function getStudentLedger(string $studentId): array
    {
        $student = Student::with(['user', 'enrollments.schoolClass', 'enrollments.section'])->findOrFail($studentId);

        $assignments = StudentFeeAssignment::with(['structure.items', 'concession'])
            ->where('student_id', $studentId)
            ->get();

        $invoices = FeeInvoice::with(['items', 'allocations.payment', 'fines'])
            ->where('student_id', $studentId)
            ->orderBy('created_at', 'asc')
            ->get();

        $payments = FeePayment::with(['allocations.invoice', 'refunds'])
            ->where('student_id', $studentId)
            ->orderBy('payment_date', 'asc')
            ->get();

        $totalInvoiced = (float) $invoices->sum('total_amount');
        $totalPaid = (float) $payments->where('status', 'successful')->sum('amount_paid');
        $totalOutstanding = (float) $invoices->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])->sum('balance_amount');
        $totalRefunded = 0.00;

        foreach ($payments as $payment) {
            $totalRefunded += (float) $payment->refunds->sum('amount');
        }

        return [
            'student' => $student,
            'summary' => [
                'total_invoiced' => round($totalInvoiced, 2),
                'total_paid' => round($totalPaid, 2),
                'total_refunded' => round($totalRefunded, 2),
                'net_paid' => round($totalPaid - $totalRefunded, 2),
                'total_outstanding' => round($totalOutstanding, 2),
            ],
            'assignments' => $assignments,
            'invoices' => $invoices,
            'payments' => $payments,
        ];
    }

    /**
     * Generate thread-safe sequential invoice number INV-YYYY-XXXX.
     */
    protected function generateInvoiceNumber(string $tenantId): string
    {
        $year = Carbon::now()->year;
        $count = FeeInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('INV-%d-%05d', $year, $count + 1);
    }
}

