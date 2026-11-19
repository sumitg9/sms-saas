<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\AdmissionConfirmRequest;
use App\Http\Requests\Student\AdmissionStatusRequest;
use App\Http\Requests\Student\StudentAdmissionRequest;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\StudentAdmission;
use App\Services\Student\AdmissionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    public function __construct(
        protected AdmissionService $admissionService,
        protected TenantContext $context
    ) {}

    /**
     * List admissions pipeline with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $query = StudentAdmission::where('branch_id', $branchId)
            ->with(['appliedClass', 'academicSession', 'student']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('class_id')) {
            $query->where('applied_class_id', $request->query('class_id'));
        }

        if ($request->filled('academic_session_id')) {
            $query->where('academic_session_id', $request->query('academic_session_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('applicant_first_name', 'ILIKE', "%{$search}%")
                    ->orWhere('applicant_last_name', 'ILIKE', "%{$search}%")
                    ->orWhere('application_number', 'ILIKE', "%{$search}%");
            });
        }

        $admissions = $query->orderByDesc('created_at')->paginate(20);

        // Counts by pipeline stage
        $counts = [
            'total' => StudentAdmission::where('branch_id', $branchId)->count(),
            'applied' => StudentAdmission::where('branch_id', $branchId)->where('status', 'applied')->count(),
            'under_review' => StudentAdmission::where('branch_id', $branchId)->where('status', 'under_review')->count(),
            'waitlisted' => StudentAdmission::where('branch_id', $branchId)->where('status', 'waitlisted')->count(),
            'approved' => StudentAdmission::where('branch_id', $branchId)->where('status', 'approved')->count(),
            'enrolled' => StudentAdmission::where('branch_id', $branchId)->where('status', 'enrolled')->count(),
            'rejected' => StudentAdmission::where('branch_id', $branchId)->where('status', 'rejected')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $admissions->items(),
            'meta' => [
                'current_page' => $admissions->currentPage(),
                'last_page' => $admissions->lastPage(),
                'total' => $admissions->total(),
                'per_page' => $admissions->perPage(),
                'pipeline_counts' => $counts,
            ],
        ]);
    }

    /**
     * Submit online or manual admission application.
     */
    public function store(StudentAdmissionRequest $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();
        $branchId = $this->resolveBranchId($request);
        $sessionId = $request->input('academic_session_id') ?? $this->resolveActiveSessionId($branchId);

        $admission = $this->admissionService->createApplication(
            $request->validated(),
            $tenantId,
            $branchId,
            $sessionId
        );

        return response()->json([
            'success' => true,
            'message' => 'Admission application submitted successfully.',
            'data' => $admission->load(['appliedClass', 'academicSession']),
        ], 201);
    }

    /**
     * Retrieve single admission record with status review history.
     */
    public function show(StudentAdmission $admission): JsonResponse
    {
        $admission->load(['appliedClass', 'academicSession', 'student']);

        return response()->json([
            'success' => true,
            'data' => $admission,
        ]);
    }

    /**
     * Update application review status (under_review, waitlisted, approved, rejected).
     */
    public function updateStatus(AdmissionStatusRequest $request, StudentAdmission $admission): JsonResponse
    {
        $updated = $this->admissionService->updateStatus(
            $admission,
            $request->input('status'),
            $request->input('remarks'),
            $request->input('waitlist_number'),
            $request->input('rejection_reason')
        );

        return response()->json([
            'success' => true,
            'message' => "Application status updated to '{$updated->status}'.",
            'data' => $updated,
        ]);
    }

    /**
     * Confirm admission: creates Student master, links Guardian, creates Enrollment.
     */
    public function confirm(AdmissionConfirmRequest $request, StudentAdmission $admission): JsonResponse
    {
        $schoolId = $this->resolveSchoolId();

        $result = $this->admissionService->confirmAdmission(
            $admission,
            $request->validated(),
            $schoolId
        );

        return response()->json([
            'success' => true,
            'message' => 'Admission confirmed. Student profile and academic enrollment created successfully.',
            'data' => $result,
        ], 201);
    }

    /**
     * List waitlisted applicants.
     */
    public function waitlist(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $waitlist = StudentAdmission::where('branch_id', $branchId)
            ->where('status', 'waitlisted')
            ->with(['appliedClass'])
            ->get()
            ->sortBy(function ($item) {
                return $item->inquiry_details['waitlist_number'] ?? 9999;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $waitlist,
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
