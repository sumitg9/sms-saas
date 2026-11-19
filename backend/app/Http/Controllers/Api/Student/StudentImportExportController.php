<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StudentBulkImportRequest;
use App\Models\AcademicSession;
use App\Models\ImportJob;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Services\Student\StudentImportExportService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentImportExportController extends Controller
{
    public function __construct(
        protected StudentImportExportService $importExportService,
        protected TenantContext $context
    ) {}

    /**
     * Downloadable template structure and sample rows for student bulk import.
     */
    public function template(): JsonResponse
    {
        $template = $this->importExportService->getImportTemplate();

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Preview and validate bulk import rows before persisting.
     */
    public function preview(StudentBulkImportRequest $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        $sessionId = $request->input('academic_session_id') ?? $this->resolveActiveSessionId($branchId);

        $result = $this->importExportService->previewImport(
            $request->input('rows'),
            $branchId,
            $sessionId
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Execute transactional bulk import.
     */
    public function import(StudentBulkImportRequest $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();
        $schoolId = $this->resolveSchoolId();
        $branchId = $this->resolveBranchId($request);
        $sessionId = $request->input('academic_session_id') ?? $this->resolveActiveSessionId($branchId);
        $userId = auth()->id();

        $result = $this->importExportService->processImport(
            $request->input('rows'),
            $tenantId,
            $schoolId,
            $branchId,
            $sessionId,
            $userId
        );

        return response()->json([
            'success' => true,
            'message' => "Import completed: {$result['processed_rows']} students created, {$result['error_rows']} skipped.",
            'data' => $result,
        ], 201);
    }

    /**
     * Export student roster with optional filtering.
     */
    public function export(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $students = $this->importExportService->exportStudents(
            $branchId,
            $request->query('academic_session_id'),
            $request->query('class_id'),
            $request->query('section_id'),
            $request->query('status')
        );

        return response()->json([
            'success' => true,
            'data' => $students,
            'meta' => [
                'total_exported' => count($students),
                'branch_id' => $branchId,
                'exported_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * List recent import jobs audit history.
     */
    public function history(Request $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();

        $jobs = ImportJob::where('tenant_id', $tenantId)
            ->where('import_type', 'students')
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    protected function resolveBranchId(Request $request): string
    {
        return $this->context->getBranchId()
            ?? $request->header('X-Branch-ID')
            ?? SchoolBranch::where('tenant_id', $this->context->getTenantId())->value('id');
    }

    protected function resolveSchoolId(): string
    {
        return $this->context->getSchoolId()
            ?? School::where('tenant_id', $this->context->getTenantId())->value('id');
    }

    protected function resolveActiveSessionId(string $branchId): string
    {
        $session = AcademicSession::where('branch_id', $branchId)
            ->where('is_current', true)
            ->first();

        return $session ? $session->id : AcademicSession::where('branch_id', $branchId)->value('id');
    }
}
