<?php

namespace App\Http\Controllers\Api\Setting;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolBranch;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolSettingController extends Controller
{
    protected TenantContext $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Get the school profile for the active tenant.
     */
    public function getSchool(): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $school = School::with('branches')->where('tenant_id', $tenantId)->first();

        if (!$school) {
            $tenant = Tenant::findOrFail($tenantId);
            $school = School::create([
                'tenant_id' => $tenantId,
                'name' => $tenant->name,
                'code' => strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $tenant->name), 0, 6)),
                'email' => $tenant->contact_email,
                'phone' => $tenant->contact_phone,
                'education_board' => 'CBSE',
                'is_active' => true,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $school,
        ]);
    }

    /**
     * Update school profile.
     */
    public function updateSchool(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $school = School::where('tenant_id', $tenantId)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'affiliation_number' => ['nullable', 'string', 'max:50'],
            'education_board' => ['required', 'string', 'in:CBSE,ICSE,State Board,Cambridge,IB,Other'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'array'],
            'logo_path' => ['nullable', 'string', 'max:500'],
            'stamp_path' => ['nullable', 'string', 'max:500'],
        ]);

        $school->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'School profile updated successfully.',
            'data' => $school,
        ]);
    }

    /**
     * List all branches for this school/tenant.
     */
    public function getBranches(): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $branches = SchoolBranch::where('tenant_id', $tenantId)
            ->withCount(['classes', 'academicSessions'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $branches,
        ]);
    }

    /**
     * Create a new branch.
     */
    public function storeBranch(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $school = School::where('tenant_id', $tenantId)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:20'],
            'campus_type' => ['nullable', 'string', 'in:main,secondary,kindergarten,sports_complex'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $branch = SchoolBranch::create(array_merge($validated, [
            'tenant_id' => $tenantId,
            'school_id' => $school->id,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully.',
            'data' => $branch,
        ], 201);
    }

    /**
     * Update an existing branch.
     */
    public function updateBranch(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $branch = SchoolBranch::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:20'],
            'campus_type' => ['nullable', 'string', 'in:main,secondary,kindergarten,sports_complex'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $branch->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully.',
            'data' => $branch,
        ]);
    }

    /**
     * Get tenant operational settings.
     */
    public function getSettings(): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $settings = TenantSetting::where('tenant_id', $tenantId)->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Save tenant operational settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $settings = $request->validate([
            'settings' => ['required', 'array'],
        ])['settings'];

        foreach ($settings as $key => $value) {
            TenantSetting::updateOrCreate(
                ['tenant_id' => $tenantId, 'key' => $key],
                ['value' => is_array($value) ? json_encode($value) : $value]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully.',
            'data' => TenantSetting::where('tenant_id', $tenantId)->pluck('value', 'key'),
        ]);
    }
}

