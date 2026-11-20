<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformAdminController extends Controller
{
    /**
     * List all tenants across the SaaS platform (Super Admin only).
     */
    public function tenants(Request $request): JsonResponse
    {
        $query = Tenant::with(['schools', 'subscriptions.plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('contact_email', 'ilike', "%{$search}%");
            });
        }

        $tenants = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $tenants->items(),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'total' => $tenants->total(),
            ],
        ]);
    }

    /**
     * Update tenant status (active, suspended, cancelled).
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended,cancelled,trial'],
            'suspension_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === 'suspended') {
            $updateData['suspended_at'] = now();
            $updateData['suspension_reason'] = $validated['suspension_reason'] ?? 'Suspended by platform administrator';
        } else {
            $updateData['suspended_at'] = null;
            $updateData['suspension_reason'] = null;
        }

        $tenant->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "Tenant status updated to {$validated['status']}.",
            'data' => $tenant,
        ]);
    }

    /**
     * Extend a tenant's trial period.
     */
    public function extendTrial(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $baseDate = ($tenant->trial_ends_at && $tenant->trial_ends_at->isFuture())
            ? $tenant->trial_ends_at
            : now();

        $newTrialEnd = $baseDate->addDays($validated['days']);

        $tenant->update([
            'trial_ends_at' => $newTrialEnd,
            'status' => 'trial',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Trial extended by {$validated['days']} days.",
            'data' => $tenant,
        ]);
    }
}

