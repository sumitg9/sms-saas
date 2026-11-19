<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Services\Student\StudentProfileService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GuardianController extends Controller
{
    public function __construct(
        protected StudentProfileService $profileService,
        protected TenantContext $context
    ) {}

    /**
     * List all guardians in the tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->context->getTenantId();

        $query = Guardian::where('tenant_id', $tenantId)
            ->withCount('students')
            ->with('user:id,email,first_name,last_name');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ILIKE', "%{$search}%")
                    ->orWhere('last_name', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        $guardians = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $guardians->items(),
            'meta' => [
                'current_page' => $guardians->currentPage(),
                'last_page' => $guardians->lastPage(),
                'total' => $guardians->total(),
                'per_page' => $guardians->perPage(),
            ],
        ]);
    }

    /**
     * Add or link a guardian to a student.
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('update', $student);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'relation_type' => ['required', 'string', 'in:father,mother,legal_guardian,other'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'annual_income' => ['nullable', 'numeric'],
            'address' => ['nullable', 'string'],
            'is_primary' => ['nullable', 'boolean'],
            'has_pickup_rights' => ['nullable', 'boolean'],
            'receives_invoices' => ['nullable', 'boolean'],
            'emergency_contact_priority' => ['nullable', 'integer', 'min:1'],
        ]);

        $guardian = $this->profileService->addGuardian($student, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Guardian linked to student successfully.',
            'data' => $guardian->load('students'),
        ], 201);
    }

    /**
     * Endpoint for Parent user to retrieve all their linked children.
     * CRITICAL: Supports multiple children under one parent account!
     */
    public function myChildren(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Find guardian profile for this user
        $guardian = Guardian::where('user_id', $user->id)
            ->where('tenant_id', $this->context->getTenantId())
            ->first();

        if (!$guardian) {
            // Also check by email/phone match
            $guardian = Guardian::where('tenant_id', $this->context->getTenantId())
                ->where(function ($q) use ($user) {
                    $q->where('email', $user->email)
                      ->orWhere('phone', $user->phone);
                })
                ->first();
        }

        if (!$guardian) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['message' => 'No student records currently linked to this guardian profile.'],
            ]);
        }

        $children = $guardian->students()
            ->with([
                'currentEnrollment.schoolClass',
                'currentEnrollment.section',
                'currentEnrollment.academicSession',
                'medicalRecord',
                'documents',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $children,
            'meta' => [
                'guardian_id' => $guardian->id,
                'guardian_name' => $guardian->full_name,
                'total_children' => $children->count(),
            ],
        ]);
    }
}
