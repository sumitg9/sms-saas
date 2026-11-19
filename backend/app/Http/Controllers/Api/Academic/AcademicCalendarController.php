<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendar;
use App\Models\AcademicEvent;
use App\Models\AcademicSession;
use App\Models\SchoolBranch;
use App\Services\Academic\AcademicCalendarService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicCalendarController extends Controller
{
    public function __construct(
        protected AcademicCalendarService $calendarService,
        protected TenantContext $context
    ) {}

    /**
     * List all calendars for the active branch.
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->resolveBranchId($request);

        $calendars = AcademicCalendar::where('branch_id', $branchId)
            ->with(['academicSession'])
            ->withCount('events')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $calendars,
        ]);
    }

    /**
     * Create an academic calendar.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_session_id' => ['required', 'uuid', 'exists:academic_sessions,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $tenantId = $this->context->getTenantId();
        $branchId = $this->resolveBranchId($request);

        $calendar = $this->calendarService->createCalendar(
            $validated,
            $tenantId,
            $branchId
        );

        return response()->json([
            'success' => true,
            'message' => 'Academic calendar created successfully.',
            'data' => $calendar,
        ], 201);
    }

    /**
     * Show calendar with full event schedule and summary.
     */
    public function show(AcademicCalendar $academicCalendar): JsonResponse
    {
        $summary = $this->calendarService->getCalendarSummary($academicCalendar);

        return response()->json([
            'success' => true,
            'data' => array_merge($academicCalendar->toArray(), [
                'summary' => [
                    'total_events' => $summary['total_events'],
                    'holidays' => $summary['holidays'],
                    'examinations' => $summary['examinations'],
                    'vacations' => $summary['vacations'],
                ],
                'events' => $summary['events'],
            ]),
        ]);
    }

    /**
     * Add an event or holiday to the calendar.
     */
    public function storeEvent(Request $request, AcademicCalendar $academicCalendar): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'event_type' => ['required', 'string', 'in:holiday,exam,event,vacation,meeting,sports'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_holiday' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $event = $this->calendarService->addEvent($academicCalendar, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Event added to academic calendar.',
            'data' => $event,
        ], 201);
    }

    /**
     * Update an event.
     */
    public function updateEvent(Request $request, AcademicEvent $academicEvent): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'event_type' => ['sometimes', 'string', 'in:holiday,exam,event,vacation,meeting,sports'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'is_holiday' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $updated = $this->calendarService->updateEvent($academicEvent, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Academic event updated.',
            'data' => $updated,
        ]);
    }

    /**
     * Delete an event.
     */
    public function destroyEvent(AcademicEvent $academicEvent): JsonResponse
    {
        $this->calendarService->deleteEvent($academicEvent);

        return response()->json([
            'success' => true,
            'message' => 'Academic event deleted successfully.',
        ]);
    }

    protected function resolveBranchId(Request $request): string
    {
        return $this->context->getBranchId()
            ?? $request->header('X-Branch-ID')
            ?? SchoolBranch::where('tenant_id', $this->context->getTenantId())->value('id');
    }
}

