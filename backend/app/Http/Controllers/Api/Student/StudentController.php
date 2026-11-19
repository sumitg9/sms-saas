<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StudentProfileRequest;
use App\Http\Requests\Student\StudentPromotionRequest;
use App\Http\Requests\Student\StudentTransferRequest;
use App\Http\Requests\Student\StudentWithdrawalRequest;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\Student;
use App\Services\Student\StudentProfileService;
use App\Services\Student\StudentProgressionService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StudentController extends Controller
{
    public function __construct(
        protected StudentProfileService $profileService,
        protected StudentProgressionService $progressionService,
        protected TenantContext $context
    ) {}

    /**
     * List all students in the branch with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $query = Student::where('branch_id', $branchId)
            ->with([
                'currentEnrollment.schoolClass',
                'currentEnrollment.section',
                'currentEnrollment.academicSession',
                'guardians',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('class_id') || $request->filled('section_id') || $request->filled('session_id')) {
            $query->whereHas('currentEnrollment', function ($q) use ($request) {
                if ($request->filled('class_id')) {
                    $q->where('class_id', $request->query('class_id'));
                }
                if ($request->filled('section_id')) {
                    $q->where('section_id', $request->query('section_id'));
                }
                if ($request->filled('session_id')) {
                    $q->where('academic_session_id', $request->query('session_id'));
                }
            });
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ILIKE', "%{$search}%")
                    ->orWhere('last_name', 'ILIKE', "%{$search}%")
                    ->orWhere('admission_number', 'ILIKE', "%{$search}%")
                    ->orWhere('national_id', 'ILIKE', "%{$search}%");
            });
        }

        $students = $query->orderBy('first_name')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $students->items(),
            'meta' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'total' => $students->total(),
                'per_page' => $students->perPage(),
            ],
        ]);
    }

    /**
     * Create student master record directly.
     */
    public function store(StudentProfileRequest $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();
        $schoolId = $this->resolveSchoolId();
        $branchId = $this->resolveBranchId($request);
        $sessionId = $request->input('academic_session_id') ?? $this->resolveActiveSessionId($branchId);

        $student = $this->profileService->createStudent(
            $request->validated(),
            $tenantId,
            $schoolId,
            $branchId,
            $sessionId
        );

        return response()->json([
            'success' => true,
            'message' => 'Student record created successfully.',
            'data' => $student,
        ], 201);
    }

    /**
     * Retrieve Student 360 profile.
     */
    public function show(Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $student->load([
            'currentEnrollment.schoolClass',
            'currentEnrollment.section',
            'currentEnrollment.academicSession',
            'enrollments.schoolClass',
            'enrollments.section',
            'enrollments.academicSession',
            'guardians',
            'documents',
            'medicalRecord',
            'promotions.fromClass',
            'promotions.toClass',
            'transfers',
        ]);

        return response()->json([
            'success' => true,
            'data' => $student,
        ]);
    }

    /**
     * Update student demographics.
     */
    public function update(StudentProfileRequest $request, Student $student): JsonResponse
    {
        Gate::authorize('update', $student);

        $updated = $this->profileService->updateStudent($student, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Student profile updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Soft delete student.
     */
    public function destroy(Student $student): JsonResponse
    {
        Gate::authorize('delete', $student);

        $student->delete();

        return response()->json([
            'success' => true,
            'message' => 'Student deleted successfully.',
        ]);
    }

    /**
     * Generate Student ID Card Data with QR Code payload.
     */
    public function idCard(Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $cardData = $this->profileService->generateStudentIdCardData($student);

        return response()->json([
            'success' => true,
            'data' => $cardData,
        ]);
    }

    /**
     * Record student withdrawal.
     */
    public function withdraw(StudentWithdrawalRequest $request, Student $student): JsonResponse
    {
        Gate::authorize('update', $student);

        $withdrawn = $this->profileService->withdrawStudent($student, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Student withdrawal recorded successfully.',
            'data' => $withdrawn,
        ]);
    }

    /**
     * Re-admit withdrawn student.
     */
    public function reAdmit(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('update', $student);

        $request->validate([
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'section_id' => ['nullable', 'uuid', 'exists:sections,id'],
            'roll_number' => ['nullable', 'string', 'max:30'],
        ]);

        $branchId = $student->branch_id;
        $sessionId = $this->resolveActiveSessionId($branchId);

        $restored = $this->profileService->reAdmitStudent($student, $request->all(), $sessionId);

        return response()->json([
            'success' => true,
            'message' => 'Student re-admitted successfully.',
            'data' => $restored,
        ]);
    }

    /**
     * Issue Transfer Certificate (TC).
     */
    public function transfer(StudentTransferRequest $request, Student $student): JsonResponse
    {
        Gate::authorize('update', $student);

        $userId = auth()->id();
        $tc = $this->profileService->issueTransferCertificate($student, $request->validated(), $userId);

        return response()->json([
            'success' => true,
            'message' => "Transfer Certificate '{$tc->tc_number}' issued successfully.",
            'data' => $tc,
        ], 201);
    }

    /**
     * Get complete academic timeline and history.
     */
    public function timeline(Student $student): JsonResponse
    {
        Gate::authorize('view', $student);

        $timeline = $this->progressionService->getAcademicTimeline($student);

        return response()->json([
            'success' => true,
            'data' => $timeline,
        ]);
    }

    /**
     * Promote student to new session.
     */
    public function promote(StudentPromotionRequest $request, Student $student): JsonResponse
    {
        Gate::authorize('update', $student);

        $result = $this->progressionService->promoteStudent(
            $student,
            $request->input('to_session_id'),
            $request->input('to_class_id'),
            $request->input('to_section_id'),
            $request->input('roll_number'),
            $request->input('status', 'promoted'),
            $request->input('remarks'),
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => "Student promoted successfully. Academic history preserved.",
            'data' => $result,
        ]);
    }

    /**
     * Bulk promote students.
     */
    public function bulkPromote(Request $request): JsonResponse
    {
        $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['uuid', 'exists:students,id'],
            'to_session_id' => ['required', 'uuid', 'exists:academic_sessions,id'],
            'to_class_id' => ['required', 'uuid', 'exists:classes,id'],
            'to_section_id' => ['required', 'uuid', 'exists:sections,id'],
        ]);

        $result = $this->progressionService->bulkPromote(
            $request->input('student_ids'),
            $request->input('to_session_id'),
            $request->input('to_class_id'),
            $request->input('to_section_id'),
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => "Batch promotion completed. {$result['promoted_count']} students promoted.",
            'data' => $result,
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
