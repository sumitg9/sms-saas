<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\StudentGatePass;
use App\Models\Visitor;
use App\Models\VisitorPass;
use App\Services\Visitor\VisitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class VisitorController extends Controller
{
    public function __construct(
        protected VisitorService $visitorService
    ) {}

    /**
     * List visitors.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Visitor::withCount('passes');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', $search)
                  ->orWhere('phone', 'ilike', $search)
                  ->orWhere('national_id', 'ilike', $search);
            });
        }

        $visitors = $query->orderBy('full_name')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $visitors->items(),
            'meta' => [
                'current_page' => $visitors->currentPage(),
                'last_page' => $visitors->lastPage(),
                'total' => $visitors->total(),
            ],
        ]);
    }

    /**
     * Register or find visitor.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'full_name' => 'required|string|max:150',
            'phone' => 'required|string|max:50',
            'national_id' => 'nullable|string|max:50',
            'photo_url' => 'nullable|string|max:500',
            'address' => 'nullable|string',
        ]);

        $visitor = $this->visitorService->findOrCreateVisitor($validated);

        return response()->json([
            'success' => true,
            'message' => 'Visitor profile saved successfully.',
            'data' => $visitor,
        ], 201);
    }

    /**
     * List visitor passes.
     */
    public function passes(Request $request): JsonResponse
    {
        $query = VisitorPass::with(['visitor', 'hostEmployee', 'pickup.student']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->boolean('inside_only')) {
            $query->where('status', 'checked_in');
        }

        $passes = $query->orderByDesc('check_in_time')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $passes->items(),
            'meta' => [
                'current_page' => $passes->currentPage(),
                'last_page' => $passes->lastPage(),
                'total' => $passes->total(),
            ],
        ]);
    }

    /**
     * Check-in visitor.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'full_name' => 'required|string|max:150',
            'phone' => 'required|string|max:50',
            'national_id' => 'nullable|string|max:50',
            'photo_url' => 'nullable|string|max:500',
            'address' => 'nullable|string',
            'host_employee_id' => 'required|uuid|exists:employees,id',
            'purpose' => 'required|string|max:255',
            'badge_printed' => 'nullable|boolean',
            'student_pickup' => 'nullable|array',
            'student_pickup.student_id' => 'required_with:student_pickup|uuid|exists:students,id',
            'student_pickup.guardian_id' => 'nullable|uuid|exists:guardians,id',
            'student_pickup.guardian_phone' => 'nullable|string|max:50',
            'student_pickup.authorization_status' => 'nullable|string|in:verified,pending,rejected',
            'student_pickup.relationship_to_student' => 'nullable|string|max:100',
            'student_pickup.parent_consent_confirmed' => 'nullable|boolean',
            'student_pickup.parent_contact_phone' => 'nullable|string|max:50',
            'student_pickup.verification_notes' => 'nullable|string',
        ]);

        $pass = $this->visitorService->checkIn($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Visitor checked in and pass generated.',
            'data' => $pass,
        ], 201);
    }

    /**
     * Check-out visitor.
     */
    public function checkOut(string $id): JsonResponse
    {
        try {
            $pass = $this->visitorService->checkOut($id);

            return response()->json([
                'success' => true,
                'message' => 'Visitor checked out successfully.',
                'data' => $pass,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Visitor history.
     */
    public function history(string $id): JsonResponse
    {
        $history = $this->visitorService->getVisitorHistory($id);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Issue early departure gate pass for student.
     */
    public function storeGatePass(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|uuid|exists:school_branches,id',
            'student_id' => 'required|uuid|exists:students,id',
            'guardian_id' => 'required|uuid|exists:guardians,id',
            'reason' => 'required|string',
        ]);

        $gatePass = $this->visitorService->issueStudentGatePass($validated, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Student gate pass issued successfully.',
            'data' => $gatePass,
        ], 201);
    }

    /**
     * Record gate exit for student gate pass.
     */
    public function exitGatePass(string $id): JsonResponse
    {
        try {
            $gatePass = $this->visitorService->recordStudentExit($id);

            return response()->json([
                'success' => true,
                'message' => 'Student exit recorded at the gate.',
                'data' => $gatePass,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reports and front desk statistics.
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

        $reports = $this->visitorService->getVisitorReports($branchId);

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }
}

