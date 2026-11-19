<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\AcademicSessionRequest;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Services\Academic\AcademicSessionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicSessionController extends Controller
{
    public function __construct(
        protected AcademicSessionService $sessionService,
        protected TenantContext $context
    ) {}

    /**
     * List all academic sessions for the current branch.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();
        $branchId = $this->resolveBranchId($request);

        $sessions = AcademicSession::where('branch_id', $branchId)
            ->withCount(['enrollments', 'timetables'])
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'meta' => [
                'current_session' => $sessions->firstWhere('is_current', true),
                'branch_id' => $branchId,
            ],
        ]);
    }

    /**
     * Create a new academic session.
     */
    public function store(AcademicSessionRequest $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();
        $schoolId = $this->resolveSchoolId();
        $branchId = $this->resolveBranchId($request);

        $session = $this->sessionService->createSession(
            $request->validated(),
            $tenantId,
            $schoolId,
            $branchId
        );

        return response()->json([
            'success' => true,
            'message' => 'Academic session created successfully.',
            'data' => $session,
        ], 201);
    }

    /**
     * Show academic session details.
     */
    public function show(AcademicSession $academicSession): JsonResponse
    {
        $academicSession->loadCount(['enrollments', 'timetables', 'feeStructures', 'examinations']);

        return response()->json([
            'success' => true,
            'data' => $academicSession,
        ]);
    }

    /**
     * Update an academic session.
     */
    public function update(AcademicSessionRequest $request, AcademicSession $academicSession): JsonResponse
    {
        $updated = $this->sessionService->updateSession($academicSession, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Academic session updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Activate the academic session (atomically deactivating others).
     */
    public function activate(AcademicSession $academicSession): JsonResponse
    {
        $activated = $this->sessionService->activateSession($academicSession);

        return response()->json([
            'success' => true,
            'message' => "Academic session '{$activated->name}' is now active.",
            'data' => $activated,
        ]);
    }

    /**
     * Close an academic session.
     */
    public function close(AcademicSession $academicSession): JsonResponse
    {
        $closed = $this->sessionService->closeSession($academicSession);

        return response()->json([
            'success' => true,
            'message' => "Academic session '{$closed->name}' closed.",
            'data' => $closed,
        ]);
    }

    /**
     * List historical (closed) academic sessions.
     */
    public function historical(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        $historical = $this->sessionService->getHistoricalSessions($branchId);

        return response()->json([
            'success' => true,
            'data' => $historical,
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
}

