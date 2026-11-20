<?php

namespace App\Http\Controllers\Api\Reporting;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExportJob;
use App\Services\Reporting\ReportingEngineService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class ReportingController extends Controller
{
    public function __construct(
        protected ReportingEngineService $reportingService
    ) {}

    /**
     * Paginated report data view.
     */
    public function report(Request $request, string $reportType): JsonResponse
    {
        try {
            $perPage = $request->integer('per_page', 25);
            $results = $this->reportingService->generateReport($reportType, $request->all(), $perPage);

            return response()->json([
                'success' => true,
                'report_type' => $reportType,
                'data' => $results->items(),
                'meta' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'total' => $results->total(),
                    'per_page' => $results->perPage(),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Download CSV export.
     */
    public function exportCsv(Request $request, string $reportType): Response
    {
        $csvData = $this->reportingService->generateCsvExport($reportType, $request->all());

        // Audit log
        AuditLog::create([
            'tenant_id' => $request->user()->active_tenant_id ?? app(\App\Services\Tenancy\TenantContext::class)->getTenantId(),
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'action' => 'exported',
            'auditable_type' => 'Report:' . ucfirst($reportType),
            'auditable_id' => $request->user()->id,
            'new_values' => [
                'report_type' => $reportType,
                'format' => 'csv',
                'filters' => $request->all(),
            ],
            'created_at' => Carbon::now(),
        ]);

        $fileName = $reportType . '_report_' . Carbon::now()->format('Ymd_His') . '.csv';

        return response($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Render Print/PDF HTML format.
     */
    public function exportPrint(Request $request, string $reportType): Response
    {
        $html = $this->reportingService->generatePrintHtml($reportType, $request->all());

        return response($html, 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * Queue asynchronous export job for large reports.
     */
    public function queueAsyncExport(Request $request, string $reportType): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'nullable|string|in:csv,xlsx,pdf',
        ]);

        $format = $validated['format'] ?? 'csv';
        $exportJob = $this->reportingService->queueAsyncExport(
            $reportType,
            $format,
            $request->except('format'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => "Asynchronous {$reportType} report export job enqueued successfully.",
            'job_id' => $exportJob->id,
            'data' => $exportJob,
        ], 202);
    }

    /**
     * Track async export job status.
     */
    public function showJob(string $id): JsonResponse
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $job,
        ]);
    }
}
