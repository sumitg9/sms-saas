<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SchoolClassRequest;
use App\Http\Requests\Academic\SectionRequest;
use App\Models\AcademicSession;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\Academic\ClassManagementService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function __construct(
        protected ClassManagementService $classService,
        protected TenantContext $context
    ) {}

    /**
     * List all classes with sections for the active branch.
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $classes = SchoolClass::where('branch_id', $branchId)
            ->with(['sections.classTeachers.employee', 'classLevel', 'board'])
            ->orderBy('order_index')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $classes,
        ]);
    }

    /**
     * Create a new class.
     */
    public function store(SchoolClassRequest $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();
        $schoolId = $this->resolveSchoolId();
        $branchId = $this->resolveBranchId($request);

        $class = $this->classService->createClass(
            $request->validated(),
            $tenantId,
            $schoolId,
            $branchId
        );

        return response()->json([
            'success' => true,
            'message' => "Class '{$class->name}' created successfully.",
            'data' => $class->load(['sections', 'board']),
        ], 201);
    }

    /**
     * Show class details.
     */
    public function show(SchoolClass $class): JsonResponse
    {
        $class->load(['sections.classTeachers.employee', 'classSubjects.subject', 'board', 'classLevel']);

        return response()->json([
            'success' => true,
            'data' => $class,
        ]);
    }

    /**
     * Update an existing class.
     */
    public function update(SchoolClassRequest $request, SchoolClass $class): JsonResponse
    {
        $updated = $this->classService->updateClass($class, $request->validated());

        return response()->json([
            'success' => true,
            'message' => "Class '{$updated->name}' updated successfully.",
            'data' => $updated->load('sections'),
        ]);
    }

    /**
     * Reorder classes.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*' => ['required', 'integer'],
        ]);

        $branchId = $this->resolveBranchId($request);
        $this->classService->reorderClasses($branchId, $validated['orders']);

        return response()->json([
            'success' => true,
            'message' => 'Classes reordered successfully.',
        ]);
    }

    /**
     * Create a section within a class.
     */
    public function storeSection(SectionRequest $request, SchoolClass $class): JsonResponse
    {
        $section = $this->classService->createSection($class, $request->validated());

        return response()->json([
            'success' => true,
            'message' => "Section '{$section->name}' added to {$class->name}.",
            'data' => $section,
        ], 201);
    }

    /**
     * Update a section.
     */
    public function updateSection(SectionRequest $request, SchoolClass $class, Section $section): JsonResponse
    {
        $updated = $this->classService->updateSection($section, $request->validated());

        return response()->json([
            'success' => true,
            'message' => "Section '{$updated->name}' updated.",
            'data' => $updated,
        ]);
    }

    /**
     * Assign a class teacher to a section for an academic session.
     */
    public function assignClassTeacher(Request $request, SchoolClass $class, Section $section): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'academic_session_id' => ['nullable', 'uuid', 'exists:academic_sessions,id'],
            'is_primary' => ['nullable', 'boolean'],
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

        $classTeacher = $this->classService->assignClassTeacher(
            $class->id,
            $section->id,
            $validated['employee_id'],
            $sessionId,
            $class->tenant_id,
            $class->branch_id,
            $validated['is_primary'] ?? true
        );

        return response()->json([
            'success' => true,
            'message' => 'Class teacher assigned successfully.',
            'data' => $classTeacher->load('employee'),
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

