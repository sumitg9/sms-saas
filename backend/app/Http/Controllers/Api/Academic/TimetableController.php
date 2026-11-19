<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\TimetableRequest;
use App\Http\Requests\Academic\TimetableSlotRequest;
use App\Http\Requests\Academic\TimetableSubstitutionRequest;
use App\Models\SchoolBranch;
use App\Models\Timetable;
use App\Models\TimetableEntry;
use App\Services\Academic\TimetableService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    public function __construct(
        protected TimetableService $timetableService,
        protected TenantContext $context
    ) {}

    /**
     * List all timetables in the active branch.
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $timetables = Timetable::where('branch_id', $branchId)
            ->with(['academicSession'])
            ->withCount(['periods', 'entries'])
            ->orderByDesc('effective_from')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $timetables,
        ]);
    }

    /**
     * Create a master timetable.
     */
    public function store(TimetableRequest $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();
        $branchId = $this->resolveBranchId($request);

        $timetable = $this->timetableService->createTimetable(
            $request->validated(),
            $tenantId,
            $branchId
        );

        return response()->json([
            'success' => true,
            'message' => "Timetable '{$timetable->name}' created successfully.",
            'data' => $timetable,
        ], 201);
    }

    /**
     * Show timetable with periods and schedule entries.
     */
    public function show(Timetable $timetable): JsonResponse
    {
        $timetable->load([
            'academicSession',
            'periods',
            'entries.schoolClass',
            'entries.section',
            'entries.subject',
            'entries.employee.user',
            'entries.substitutions.substituteTeacher.user',
        ]);

        return response()->json([
            'success' => true,
            'data' => $timetable,
        ]);
    }

    /**
     * Configure periods and breaks for the timetable.
     */
    public function configurePeriods(Request $request, Timetable $timetable): JsonResponse
    {
        $validated = $request->validate([
            'periods' => ['required', 'array', 'min:1'],
            'periods.*.period_number' => ['required', 'integer'],
            'periods.*.period_name' => ['required', 'string', 'max:50'],
            'periods.*.start_time' => ['required', 'date_format:H:i'],
            'periods.*.end_time' => ['required', 'date_format:H:i'],
            'periods.*.is_break' => ['nullable', 'boolean'],
        ]);

        $periods = $this->timetableService->configurePeriods($timetable, $validated['periods']);

        return response()->json([
            'success' => true,
            'message' => 'Timetable periods configured successfully.',
            'data' => $periods,
        ]);
    }

    /**
     * Assign a teaching slot with conflict detection.
     */
    public function assignSlot(TimetableSlotRequest $request, Timetable $timetable): JsonResponse
    {
        $entry = $this->timetableService->assignSlot($timetable, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Timetable period slot assigned successfully.',
            'data' => $entry->load(['schoolClass', 'section', 'subject', 'employee.user', 'period']),
        ], 201);
    }

    /**
     * Pre-flight conflict check API endpoint.
     */
    public function checkConflicts(TimetableSlotRequest $request, Timetable $timetable): JsonResponse
    {
        $slotData = array_merge($request->validated(), ['timetable_id' => $timetable->id]);
        $excludeEntryId = $request->query('exclude_entry_id');

        $result = $this->timetableService->detectSlotConflicts($slotData, $excludeEntryId);

        return response()->json([
            'success' => true,
            'has_conflict' => $result['has_conflict'],
            'conflicts' => $result['conflicts'],
        ]);
    }

    /**
     * Assign a substitute teacher for a timetable entry.
     */
    public function assignSubstitute(TimetableSubstitutionRequest $request, TimetableEntry $entry): JsonResponse
    {
        $validated = $request->validated();

        $substitution = $this->timetableService->assignSubstitute(
            $entry,
            $validated['substitute_employee_id'],
            $validated['date'],
            $validated['reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Substitute teacher assigned successfully.',
            'data' => $substitution->load(['substituteTeacher.user', 'originalTeacher.user', 'entry.period']),
        ], 201);
    }

    protected function resolveBranchId(Request $request): string
    {
        return $this->context->getBranchId()
            ?? $request->header('X-Branch-ID')
            ?? SchoolBranch::where('tenant_id', $this->context->getTenantId())->value('id');
    }
}

