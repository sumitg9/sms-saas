<?php

namespace App\Services\Reporting;

use App\Jobs\Reporting\ProcessExportJob;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\ExamResult;
use App\Models\Expense;
use App\Models\ExportJob;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\InventoryItem;
use App\Models\LibraryBook;
use App\Models\Student;
use App\Models\StudentAdmission;
use App\Models\StudentAttendance;
use App\Models\TransportRoute;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReportingEngineService
{
    /**
     * Supported report types.
     */
    public const SUPPORTED_REPORTS = [
        'students',
        'admissions',
        'attendance',
        'teachers',
        'staff',
        'fees',
        'expenses',
        'examinations',
        'transport',
        'library',
        'inventory',
    ];

    /**
     * Query report dataset with pagination and filters.
     */
    public function generateReport(string $reportType, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->buildQuery($reportType, $filters);
        return $query->paginate($perPage);
    }

    /**
     * Build base query with multi-domain filters.
     */
    public function buildQuery(string $reportType, array $filters = [])
    {
        if (!in_array($reportType, self::SUPPORTED_REPORTS)) {
            throw new InvalidArgumentException("Unsupported report type: {$reportType}");
        }

        switch ($reportType) {
            case 'students':
                $q = Student::with(['enrollments.schoolClass', 'enrollments.section']);
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                if (!empty($filters['status'])) $q->where('status', $filters['status']);
                if (!empty($filters['search'])) {
                    $s = '%' . $filters['search'] . '%';
                    $q->where(fn($sub) => $sub->where('first_name', 'ilike', $s)->orWhere('last_name', 'ilike', $s)->orWhere('admission_number', 'ilike', $s));
                }
                if (!empty($filters['class_id'])) {
                    $q->whereHas('enrollments', fn($sub) => $sub->where('class_id', $filters['class_id']));
                }
                return $q->orderBy('admission_number');

            case 'admissions':
                $q = StudentAdmission::with(['schoolClass', 'academicSession']);
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                if (!empty($filters['status'])) $q->where('status', $filters['status']);
                if (!empty($filters['start_date'])) $q->whereDate('created_at', '>=', $filters['start_date']);
                if (!empty($filters['end_date'])) $q->whereDate('created_at', '<=', $filters['end_date']);
                return $q->orderByDesc('created_at');

            case 'attendance':
                $q = StudentAttendance::with(['student', 'schoolClass', 'section']);
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                if (!empty($filters['class_id'])) $q->where('class_id', $filters['class_id']);
                if (!empty($filters['section_id'])) $q->where('section_id', $filters['section_id']);
                if (!empty($filters['start_date'])) $q->where('date', '>=', $filters['start_date']);
                if (!empty($filters['end_date'])) $q->where('date', '<=', $filters['end_date']);
                return $q->orderByDesc('date');

            case 'teachers':
                $q = Employee::whereHas('designation', function ($sub) {
                    $sub->where('title', 'ilike', '%teacher%')
                        ->orWhere('code', 'ilike', '%tch%');
                })->with(['department', 'designation']);
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                if (!empty($filters['status'])) $q->where('status', $filters['status']);
                return $q->orderBy('employee_code');

            case 'staff':
                $q = Employee::with(['department', 'designation']);
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                if (!empty($filters['status'])) $q->where('status', $filters['status']);
                return $q->orderBy('employee_code');

            case 'fees':
                $q = FeePayment::with(['student']);
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                if (!empty($filters['status'])) $q->where('status', $filters['status']);
                if (!empty($filters['start_date'])) $q->where('payment_date', '>=', $filters['start_date']);
                if (!empty($filters['end_date'])) $q->where('payment_date', '<=', $filters['end_date']);
                return $q->orderByDesc('payment_date');

            case 'expenses':
                $q = Expense::with('category');
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                if (!empty($filters['start_date'])) $q->where('expense_date', '>=', $filters['start_date']);
                if (!empty($filters['end_date'])) $q->where('expense_date', '<=', $filters['end_date']);
                return $q->orderByDesc('expense_date');

            case 'examinations':
                $q = ExamResult::with(['student', 'examSchedule.examination']);
                if (!empty($filters['exam_id'])) {
                    $q->whereHas('examSchedule', fn($sub) => $sub->where('examination_id', $filters['exam_id']));
                }
                return $q->orderByDesc('percentage');

            case 'transport':
                $q = TransportRoute::with(['vehicle', 'driver.employee', 'stops'])
                    ->withCount('allocations');
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                return $q->orderBy('route_name');

            case 'library':
                $q = LibraryBook::with('copies');
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                if (!empty($filters['category'])) $q->where('category', $filters['category']);
                return $q->orderBy('title');

            case 'inventory':
                $q = InventoryItem::with('category');
                if (!empty($filters['branch_id'])) $q->where('branch_id', $filters['branch_id']);
                return $q->orderBy('item_name');
        }

        throw new InvalidArgumentException("Unknown query mapping for: {$reportType}");
    }

    /**
     * Generate CSV export format.
     */
    public function generateCsvExport(string $reportType, array $filters = []): string
    {
        $records = $this->buildQuery($reportType, $filters)->get();
        $output = fopen('php://temp', 'r+');

        switch ($reportType) {
            case 'students':
                fputcsv($output, ['Admission Number', 'First Name', 'Last Name', 'Gender', 'Status', 'Admission Date']);
                foreach ($records as $r) {
                    fputcsv($output, [$r->admission_number, $r->first_name, $r->last_name, $r->gender, $r->status, $r->admission_date?->toDateString()]);
                }
                break;

            case 'fees':
                fputcsv($output, ['Receipt Number', 'Payment Date', 'Amount', 'Payment Method', 'Status']);
                foreach ($records as $r) {
                    fputcsv($output, [$r->receipt_number, $r->payment_date?->toDateString(), $r->amount_paid, $r->payment_method, $r->status]);
                }
                break;

            case 'attendance':
                fputcsv($output, ['Date', 'Student ID', 'Status', 'Remarks']);
                foreach ($records as $r) {
                    $d = $r->date instanceof \DateTimeInterface ? $r->date->format('Y-m-d') : (string) $r->date;
                    fputcsv($output, [$d, $r->student_id, $r->status, $r->remarks]);
                }
                break;

            case 'inventory':
                fputcsv($output, ['Item Code', 'Item Name', 'Unit', 'Current Stock', 'Min Alert']);
                foreach ($records as $r) {
                    fputcsv($output, [$r->item_code, $r->item_name, $r->unit_of_measure, $r->current_stock, $r->min_stock_alert]);
                }
                break;

            default:
                fputcsv($output, ['ID', 'Created At']);
                foreach ($records as $r) {
                    fputcsv($output, [$r->id, $r->created_at?->toIso8601String()]);
                }
                break;
        }

        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }

    /**
     * Generate Print-ready HTML document.
     */
    public function generatePrintHtml(string $reportType, array $filters = []): string
    {
        $records = $this->buildQuery($reportType, $filters)->take(500)->get();
        $title = strtoupper($reportType) . " REPORT - " . Carbon::today()->toFormattedDateString();

        $rowsHtml = "";
        foreach ($records as $r) {
            if ($reportType === 'students') {
                $rowsHtml .= "<tr><td>{$r->admission_number}</td><td>{$r->first_name} {$r->last_name}</td><td>{$r->gender}</td><td>{$r->status}</td></tr>";
            } elseif ($reportType === 'fees') {
                $rowsHtml .= "<tr><td>{$r->receipt_number}</td><td>{$r->payment_date}</td><td>₹{$r->amount}</td><td>{$r->payment_method}</td><td>{$r->status}</td></tr>";
            } else {
                $rowsHtml .= "<tr><td>{$r->id}</td><td>{$r->created_at}</td></tr>";
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; padding: 25px; color: #333; }
        h1 { font-size: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 12px; text-align: left; }
        th { background: #f8fafc; font-weight: 600; color: #475569; }
        tr:nth-child(even) { background: #fdfdfd; }
        .meta { font-size: 11px; color: #64748b; margin-top: 25px; text-align: right; }
    </style>
</head>
<body onload="window.print()">
    <h1>{$title}</h1>
    <table>
        <thead>
            <tr><th>Identifier</th><th>Details</th><th>Status / Additional</th><th>Timestamp</th></tr>
        </thead>
        <tbody>
            {$rowsHtml}
        </tbody>
    </table>
    <div class="meta">Generated automatically by School ERP SaaS Platform on {$title}</div>
</body>
</html>
HTML;
    }

    /**
     * Dispatch asynchronous export background job for large reports.
     */
    public function queueAsyncExport(string $reportType, string $format, array $filters, User $user): ExportJob
    {
        $exportJob = ExportJob::create([
            'tenant_id' => $user->active_tenant_id ?? app(\App\Services\Tenancy\TenantContext::class)->getTenantId(),
            'user_id' => $user->id,
            'export_type' => $reportType,
            'format' => $format,
            'status' => 'pending',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        // Dispatch background worker
        ProcessExportJob::dispatch($exportJob->id, $filters);

        // Audit log
        AuditLog::create([
            'tenant_id' => $exportJob->tenant_id,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'action' => 'exported',
            'auditable_type' => 'App\Models\ExportJob',
            'auditable_id' => $exportJob->id,
            'new_values' => [
                'report_type' => $reportType,
                'format' => $format,
                'filters' => $filters,
            ],
            'created_at' => Carbon::now(),
        ]);

        return $exportJob;
    }
}
