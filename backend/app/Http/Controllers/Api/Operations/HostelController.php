<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\HostelBuilding;
use App\Models\HostelComplaint;
use App\Models\HostelMessMenu;
use App\Models\HostelRoom;
use App\Services\Hostel\HostelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class HostelController extends Controller
{
    public function __construct(
        protected HostelService $hostelService
    ) {}

    /**
     * List buildings.
     */
    public function buildings(Request $request): JsonResponse
    {
        $query = HostelBuilding::withCount('rooms');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    /**
     * Store building.
     */
    public function storeBuilding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'name' => 'required|string|max:100',
            'building_type' => 'required|string|in:boys,girls,co_ed',
            'warden_name' => 'nullable|string|max:100',
            'warden_phone' => 'nullable|string|max:50',
        ]);

        $building = $this->hostelService->createBuilding($validated);

        return response()->json([
            'success' => true,
            'message' => 'Hostel building created successfully.',
            'data' => $building,
        ], 201);
    }

    /**
     * List rooms.
     */
    public function rooms(Request $request): JsonResponse
    {
        $query = HostelRoom::with(['building', 'activeAllocations.student']);

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->query('building_id'));
        }

        if ($request->boolean('available_only')) {
            $query->where('is_active', true)
                  ->whereColumn('occupied_beds', '<', 'capacity');
        }

        $rooms = $query->orderBy('room_number')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $rooms->items(),
            'meta' => [
                'current_page' => $rooms->currentPage(),
                'last_page' => $rooms->lastPage(),
                'total' => $rooms->total(),
            ],
        ]);
    }

    /**
     * Store room.
     */
    public function storeRoom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'building_id' => 'required|uuid|exists:hostel_buildings,id',
            'room_number' => 'required|string|max:50',
            'room_type' => 'required|string|in:single,double,dormitory',
            'capacity' => 'required|integer|min:1|max:50',
            'fee_per_session' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $room = $this->hostelService->createRoom($validated);

        return response()->json([
            'success' => true,
            'message' => 'Hostel room added successfully.',
            'data' => $room,
        ], 201);
    }

    /**
     * Show room.
     */
    public function showRoom(string $id): JsonResponse
    {
        $room = HostelRoom::with(['building', 'activeAllocations.student.user'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $room,
        ]);
    }

    /**
     * Allocate student to hostel room.
     */
    public function allocate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|uuid|exists:hostel_rooms,id',
            'student_id' => 'required|uuid|exists:students,id',
            'enrollment_id' => 'required|uuid|exists:student_enrollments,id',
            'bed_number' => 'nullable|string|max:20',
            'allocation_date' => 'nullable|date',
        ]);

        try {
            $allocation = $this->hostelService->allocateStudent($validated);

            return response()->json([
                'success' => true,
                'message' => 'Student allocated to hostel room successfully.',
                'data' => $allocation,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Vacate student from room.
     */
    public function vacate(Request $request, string $allocationId): JsonResponse
    {
        $validated = $request->validate([
            'vacating_date' => 'nullable|date',
        ]);

        try {
            $allocation = $this->hostelService->vacateStudent(
                $allocationId,
                $validated['vacating_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Hostel room vacated successfully.',
                'data' => $allocation,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Record daily hostel attendance.
     */
    public function attendance(Request $request, string $buildingId): JsonResponse
    {
        $validated = $request->validate([
            'attendance_date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|uuid|exists:students,id',
            'records.*.status' => 'required|string|in:present,absent,on_leave,sick',
            'records.*.remarks' => 'nullable|string|max:255',
        ]);

        $attendances = $this->hostelService->recordAttendance(
            $buildingId,
            $validated['records'],
            $validated['attendance_date'],
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Hostel attendance recorded successfully.',
            'data' => $attendances,
        ]);
    }

    /**
     * Get mess menu for building.
     */
    public function getMessMenu(string $buildingId): JsonResponse
    {
        $menu = HostelMessMenu::where('building_id', $buildingId)->get();

        return response()->json([
            'success' => true,
            'data' => $menu,
        ]);
    }

    /**
     * Set weekly mess menu.
     */
    public function setMessMenu(Request $request, string $buildingId): JsonResponse
    {
        $validated = $request->validate([
            'day_of_week' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'meal_type' => 'required|string|in:breakfast,lunch,snacks,dinner',
            'menu_items' => 'required|array|min:1',
            'special_notes' => 'nullable|string',
        ]);

        $menu = $this->hostelService->setMessMenu($buildingId, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Mess menu updated successfully.',
            'data' => $menu,
        ]);
    }

    /**
     * List complaints.
     */
    public function complaints(Request $request): JsonResponse
    {
        $query = HostelComplaint::with(['building', 'room', 'student', 'resolver']);

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->query('building_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $complaints = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $complaints->items(),
            'meta' => [
                'current_page' => $complaints->currentPage(),
                'last_page' => $complaints->lastPage(),
                'total' => $complaints->total(),
            ],
        ]);
    }

    /**
     * File a complaint.
     */
    public function fileComplaint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'building_id' => 'required|uuid|exists:hostel_buildings,id',
            'room_id' => 'nullable|uuid|exists:hostel_rooms,id',
            'student_id' => 'required|uuid|exists:students,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'category' => 'nullable|string|in:plumbing,electrical,cleanliness,food,security,other',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
        ]);

        $complaint = $this->hostelService->fileComplaint($validated);

        return response()->json([
            'success' => true,
            'message' => 'Hostel complaint logged successfully.',
            'data' => $complaint,
        ], 201);
    }

    /**
     * Resolve a complaint.
     */
    public function resolveComplaint(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:resolved,closed',
            'resolution_notes' => 'required|string',
        ]);

        $complaint = $this->hostelService->resolveComplaint(
            $id,
            $validated,
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Complaint marked as resolved.',
            'data' => $complaint,
        ]);
    }

    /**
     * Hostel reports.
     */
    public function reports(Request $request): JsonResponse
    {
        $branchId = $request->query('branch_id');
        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'branch_id query parameter is required.',
            ], 422);
        }

        $reports = $this->hostelService->getHostelReports($branchId);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }
}

