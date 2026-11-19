<?php

namespace App\Jobs;

use App\Jobs\Traits\TenantAwareJob;
use App\Models\Student;
use App\Services\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TestTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAwareJob;

    public array $processedStudentIds = [];
    public ?string $executedTenantId = null;

    public function __construct()
    {
        $this->captureTenantContext();
    }

    /**
     * Execute the job.
     */
    public function handle(TenantContext $context): void
    {
        $this->executedTenantId = $context->getTenantId();
        // Query students using standard Eloquent (TenantScope should automatically restrict to $this->tenantId)
        $this->processedStudentIds = Student::pluck('id')->toArray();
    }
}

