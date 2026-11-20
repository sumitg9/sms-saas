<?php

namespace App\Jobs\Reporting;

use App\Models\ExportJob;
use App\Services\Reporting\ReportingEngineService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $exportJobId,
        public array $filters = []
    ) {}

    public function handle(ReportingEngineService $reportingService): void
    {
        $exportJob = ExportJob::withoutGlobalScopes()->find($this->exportJobId);
        if (!$exportJob) {
            return;
        }

        $exportJob->update(['status' => 'processing']);

        try {
            $csvContent = $reportingService->generateCsvExport(
                $exportJob->export_type,
                $this->filters
            );

            $fileName = 'exports/' . $exportJob->export_type . '_' . Carbon::now()->timestamp . '.' . $exportJob->format;
            Storage::disk('local')->put($fileName, $csvContent);

            $exportJob->update([
                'status' => 'completed',
                'file_path' => $fileName,
                'expires_at' => Carbon::now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            $exportJob->update([
                'status' => 'failed',
            ]);
        }
    }
}

