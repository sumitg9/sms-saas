<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SubjectRequest;
use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectGroup;
use App\Services\Academic\SubjectManagementService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function __construct(
        protected SubjectManagementService $subjectService,
        protected TenantContext $context
    ) {}

    /**
     * List all subjects in the branch.
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $subjects = Subject::where('branch_id', $branchId)
            ->withCount('classSubjects')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }

    /**
     * Create a new subject.
     */
    public function store(SubjectRequest $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();
        $schoolId = $this->resolveSchoolId();
        $branchId = $this->resolveBranchId($request);

        $subject = $this->subjectService->createSubject(
            $request->validated(),
            $tenantId,
            $schoolId,
            $branchId
        );

        return response()->json([
            'success' => true,
            'message' => "Subject '{$subject->name}' created successfully.",
            'data' => $subject,
        ], 201);
    }

    /**
     * Update an existing subject.
     */
    public function update(SubjectRequest $request, Subject $subject): JsonResponse
    {
        $updated = $this->subjectService->updateSubject($subject, $request->validated());

        return response()->json([
            'success' => true,
            'message' => "Subject '{$updated->name}' updated successfully.",
            'data' => $updated,
        ]);
    }

    /**
     * Assign a subject to a class.
     */
    public function assignToClass(Request $request, SchoolClass $class): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'academic_session_id' => ['nullable', 'uuid', 'exists:academic_sessions,id'],
            'is_elective' => ['nullable', 'boolean'],
            'max_weekly_periods' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $sessionId = $validated['academic_session_id'] ?? AcademicSession::where('branch_id', $class->branch_id)
            ->where('is_current', true)
            ->value('id');

        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'No active academic session found. Please specify academic_session_id.',
            ], 422);
        }

        $payload = array_merge($validated, [
            'class_id' => $class->id,
            'academic_session_id' => $sessionId,
        ]);

        $classSubject = $this->subjectService->assignSubjectToClass(
            $payload,
            $class->tenant_id,
            $class->branch_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Subject assigned to class successfully.',
            'data' => $classSubject->load('subject'),
        ], 201);
    }

    /**
     * Assign a teacher to a class subject.
     */
    public function assignTeacher(Request $request, ClassSubject $classSubject): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $teacherSubject = $this->subjectService->assignTeacherToSubject(
            $classSubject->id,
            $validated['employee_id'],
            $classSubject->tenant_id,
            $classSubject->branch_id,
            $validated['is_primary'] ?? true
        );

        return response()->json([
            'success' => true,
            'message' => 'Teacher assigned to subject successfully.',
            'data' => $teacherSubject->load('employee'),
        ]);
    }

    /**
     * List subject groups.
     */
    public function indexGroups(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);
        $groups = SubjectGroup::where('branch_id', $branchId)->get();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    /**
     * Create a subject group.
     */
    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $tenantId = $this->context->getTenantId();
        $branchId = $this->resolveBranchId($request);

        $group = $this->subjectService->createSubjectGroup(
            $validated,
            $tenantId,
            $branchId
        );

        return response()->json([
            'success' => true,
            'message' => "Subject group '{$group->name}' created successfully.",
            'data' => $group,
        ], 201);
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

