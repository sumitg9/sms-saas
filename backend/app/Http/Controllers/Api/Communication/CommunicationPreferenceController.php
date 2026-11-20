<?php

namespace App\Http\Controllers\Api\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\CommunicationPreferenceRequest;
use App\Models\CommunicationPreference;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationPreferenceController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function getPreferences(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();
        $user = $request->user();

        $pref = CommunicationPreference::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
            ],
            [
                'email_enabled' => true,
                'sms_enabled' => true,
                'whatsapp_enabled' => true,
                'push_enabled' => true,
                'emergency_override' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $pref,
        ]);
    }

    public function updatePreferences(CommunicationPreferenceRequest $request): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId();
        $user = $request->user();

        $pref = CommunicationPreference::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
            ]
        );

        $pref->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Communication preferences updated successfully.',
            'data' => $pref,
        ]);
    }
}

