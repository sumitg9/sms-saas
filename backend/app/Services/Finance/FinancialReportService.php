<?php

namespace App\Services\Finance;

use App\Models\Expense;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeRefund;
use App\Models\FinancialAccount;
use App\Models\SchoolClass;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Daily collection report.
     */
    public function getDailyCollection(string $date, ?string $branchId = null): array
    {
        $branchId = $branchId ?? $this->tenantContext->getBranchId();

        $payments = FeePayment::with(['student.user', 'collector'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('payment_date', $date)
            ->where('status', 'successful')
            ->orderBy('payment_date', 'asc')
            ->get();

        $totalAmount = (float) $payments->sum('amount_paid');

        $byMethod = $payments->groupBy('payment_method')->map(function ($items, $method) {
            return [
                'method' => $method,
                'count' => $items->count(),
                'total_amount' => round((float) $items->sum('amount_paid'), 2),
            ];
        })->values();

        return [
            'date' => $date,
            'total_amount' => round($totalAmount, 2),
            'total_transactions' => $payments->count(),
            'breakdown_by_method' => $byMethod,
            'payments' => $payments,
        ];
    }

    /**
     * Monthly collection report.
     */
    public function getMonthlyCollection(int $year, int $month, ?string $branchId = null): array
    {
        $branchId = $branchId ?? $this->tenantContext->getBranchId();
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $payments = FeePayment::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where('status', 'successful')
            ->get();

        $totalAmount = (float) $payments->sum('amount_paid');

        // Group by day of month
        $dailyTrend = [];
        $daysInMonth = $startDate->daysInMonth;
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayDate = Carbon::createFromDate($year, $month, $day)->toDateString();
            $daySum = (float) $payments->filter(function ($p) use ($dayDate) {
                return Carbon::parse($p->payment_date)->toDateString() === $dayDate;
            })->sum('amount_paid');

            $dailyTrend[] = [
                'day' => $day,
                'date' => $dayDate,
                'amount' => round($daySum, 2),
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'total_amount' => round($totalAmount, 2),
            'transaction_count' => $payments->count(),
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * Class-wise fee collection & outstanding report.
     */
    public function getClassWiseCollection(?string $academicSessionId = null, ?string $branchId = null): array
    {
        $branchId = $branchId ?? $this->tenantContext->getBranchId();

        $classes = SchoolClass::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('order_index')
            ->get();

        $results = [];

        foreach ($classes as $class) {
            $invoices = FeeInvoice::query()
                ->whereHas('enrollment', fn ($q) => $q->where('class_id', $class->id))
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($academicSessionId, fn ($q) => $q->where('academic_session_id', $academicSessionId))
                ->get();

            $totalInvoiced = (float) $invoices->sum('total_amount');
            $totalCollected = (float) $invoices->sum('paid_amount');
            $totalOutstanding = (float) $invoices->sum('balance_amount');
            $collectionRate = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 2) : 0.00;

            $results[] = [
                'class_id' => $class->id,
                'class_name' => $class->name,
                'total_invoiced' => round($totalInvoiced, 2),
                'total_collected' => round($totalCollected, 2),
                'total_outstanding' => round($totalOutstanding, 2),
                'collection_rate_percent' => $collectionRate,
            ];
        }

        return $results;
    }

    /**
     * Outstanding fees report.
     */
    public function getOutstandingReport(?string $branchId = null, ?string $classId = null): array
    {
        $branchId = $branchId ?? $this->tenantContext->getBranchId();

        $invoices = FeeInvoice::with(['student.user', 'enrollment.schoolClass', 'enrollment.section'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($classId, fn ($q) => $q->whereHas('enrollment', fn ($eq) => $eq->where('class_id', $classId)))
            ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
            ->orderBy('due_date', 'asc')
            ->get();

        $totalOutstanding = (float) $invoices->sum('balance_amount');

        $list = $invoices->map(function ($inv) {
            $daysOverdue = max(0, Carbon::today()->diffInDays(Carbon::parse($inv->due_date), false) * -1);
            return [
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'student_id' => $inv->student_id,
                'student_name' => $inv->student->first_name . ' ' . $inv->student->last_name,
                'admission_number' => $inv->student->admission_number,
                'class_name' => $inv->enrollment?->schoolClass?->name ?? 'N/A',
                'section_name' => $inv->enrollment?->section?->name ?? 'N/A',
                'due_date' => $inv->due_date,
                'days_overdue' => $daysOverdue,
                'total_amount' => (float) $inv->total_amount,
                'paid_amount' => (float) $inv->paid_amount,
                'balance_amount' => (float) $inv->balance_amount,
                'status' => $inv->status,
            ];
        });

        return [
            'total_outstanding' => round($totalOutstanding, 2),
            'count' => $invoices->count(),
            'invoices' => $list,
        ];
    }

    /**
     * Defaulters report (students with fees overdue beyond threshold days).
     */
    public function getDefaultersReport(?string $branchId = null, int $minOverdueDays = 15): array
    {
        $branchId = $branchId ?? $this->tenantContext->getBranchId();
        $cutoffDate = Carbon::today()->subDays($minOverdueDays);

        $invoices = FeeInvoice::with(['student.user', 'student.guardians', 'enrollment.schoolClass', 'enrollment.section'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
            ->where('due_date', '<=', $cutoffDate)
            ->orderBy('due_date', 'asc')
            ->get();

        // Group by student
        $defaulters = $invoices->groupBy('student_id')->map(function ($studentInvoices) {
            $firstInv = $studentInvoices->first();
            $student = $firstInv->student;
            $overdueInvoices = $studentInvoices->count();
            $totalOverdue = (float) $studentInvoices->sum('balance_amount');
            $oldestDueDate = $studentInvoices->min('due_date');
            $maxDaysOverdue = Carbon::today()->diffInDays(Carbon::parse($oldestDueDate));

            $guardian = $student->guardians?->first();

            return [
                'student_id' => $student->id,
                'student_name' => $student->first_name . ' ' . $student->last_name,
                'admission_number' => $student->admission_number,
                'class' => $firstInv->enrollment?->schoolClass?->name ?? 'N/A',
                'section' => $firstInv->enrollment?->section?->name ?? 'N/A',
                'guardian_name' => $guardian ? ($guardian->first_name . ' ' . $guardian->last_name) : 'N/A',
                'contact_phone' => $student->user?->phone ?? $guardian?->phone ?? 'N/A',
                'overdue_invoices_count' => $overdueInvoices,
                'total_overdue_amount' => round($totalOverdue, 2),
                'oldest_due_date' => $oldestDueDate,
                'days_overdue' => $maxDaysOverdue,
            ];
        })->values();

        return [
            'min_overdue_days' => $minOverdueDays,
            'total_defaulters' => $defaulters->count(),
            'total_defaulter_amount' => round($defaulters->sum('total_overdue_amount'), 2),
            'defaulters' => $defaulters,
        ];
    }

    /**
     * Payment method breakdown report.
     */
    public function getPaymentMethodBreakdown(?string $startDate = null, ?string $endDate = null, ?string $branchId = null): array
    {
        $branchId = $branchId ?? $this->tenantContext->getBranchId();

        $query = FeePayment::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'successful');

        if ($startDate) {
            $query->whereDate('payment_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('payment_date', '<=', $endDate);
        }

        $payments = $query->get();

        $totalCollected = (float) $payments->sum('amount_paid');

        $breakdown = $payments->groupBy('payment_method')->map(function ($items, $method) use ($totalCollected) {
            $amount = (float) $items->sum('amount_paid');
            $percentage = $totalCollected > 0 ? round(($amount / $totalCollected) * 100, 2) : 0.00;

            return [
                'method' => $method,
                'count' => $items->count(),
                'total_amount' => round($amount, 2),
                'percentage' => $percentage,
            ];
        })->values();

        return [
            'total_collected' => round($totalCollected, 2),
            'total_transactions' => $payments->count(),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Refund summary report.
     */
    public function getRefundSummary(?string $startDate = null, ?string $endDate = null, ?string $branchId = null): array
    {
        $refunds = FeeRefund::with(['payment.student.user', 'approver'])
            ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate))
            ->where('status', 'processed')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalRefunded = (float) $refunds->sum('amount');

        return [
            'total_refunded' => round($totalRefunded, 2),
            'total_count' => $refunds->count(),
            'refunds' => $refunds,
        ];
    }

    /**
     * Overall financial position summary.
     */
    public function getFinancialSummary(?string $startDate = null, ?string $endDate = null, ?string $branchId = null): array
    {
        $branchId = $branchId ?? $this->tenantContext->getBranchId();

        $feeQuery = FeePayment::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'successful');

        $expenseQuery = Expense::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        if ($startDate) {
            $feeQuery->whereDate('payment_date', '>=', $startDate);
            $expenseQuery->whereDate('expense_date', '>=', $startDate);
        }
        if ($endDate) {
            $feeQuery->whereDate('payment_date', '<=', $endDate);
            $expenseQuery->whereDate('expense_date', '<=', $endDate);
        }

        $totalFeeCollected = (float) $feeQuery->sum('amount_paid');
        $totalExpenses = (float) $expenseQuery->sum('amount');
        $netOperatingCash = round($totalFeeCollected - $totalExpenses, 2);

        $accounts = FinancialAccount::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('is_active', true)
            ->get();

        $totalAccountBalance = (float) $accounts->sum('current_balance');

        return [
            'total_fee_collected' => round($totalFeeCollected, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_operating_cash' => $netOperatingCash,
            'total_liquid_accounts_balance' => round($totalAccountBalance, 2),
            'accounts' => $accounts,
        ];
    }
}
