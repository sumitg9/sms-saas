<?php

namespace App\Http\Controllers\Api\Setting;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\Tenancy\DomainVerificationService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomDomainController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected DomainVerificationService $domainService
    ) {}

    /**
     * List all registered domains for the active school tenant.
     */
    public function index(): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $tenant = Tenant::findOrFail($tenantId);

        $domains = TenantDomain::where('tenant_id', $tenantId)->get();

        $domainsWithInstructions = $domains->map(function ($dom) {
            return [
                'id' => $dom->id,
                'domain' => $dom->domain,
                'is_primary' => $dom->is_primary,
                'is_verified' => $dom->is_verified,
                'ssl_status' => $dom->ssl_status,
                'created_at' => $dom->created_at,
                'dns_instructions' => !$dom->is_verified ? $this->domainService->getDnsInstructions($dom) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'default_subdomain' => "{$tenant->slug}.example.com",
                'custom_domains' => $domainsWithInstructions,
            ],
        ]);
    }

    /**
     * Register a new custom domain for verification.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $tenant = Tenant::findOrFail($tenantId);

        $tenantDomain = $this->domainService->registerDomain($tenant, $request->input('domain'));

        return response()->json([
            'success' => true,
            'message' => 'Custom domain added. Please configure DNS records to verify ownership.',
            'data' => [
                'id' => $tenantDomain->id,
                'domain' => $tenantDomain->domain,
                'is_verified' => $tenantDomain->is_verified,
                'ssl_status' => $tenantDomain->ssl_status,
                'dns_instructions' => $this->domainService->getDnsInstructions($tenantDomain),
            ],
        ], 201);
    }

    /**
     * Trigger domain ownership verification check.
     */
    public function verify(string $id): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $tenantDomain = TenantDomain::where('tenant_id', $tenantId)->findOrFail($id);

        $isVerified = $this->domainService->verifyDomainOwnership($tenantDomain);

        if ($isVerified) {
            return response()->json([
                'success' => true,
                'message' => "Domain '{$tenantDomain->domain}' has been successfully verified and activated!",
                'data' => [
                    'id' => $tenantDomain->id,
                    'domain' => $tenantDomain->domain,
                    'is_verified' => true,
                    'ssl_status' => 'active',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'code' => 'DNS_VERIFICATION_PENDING',
            'message' => "DNS TXT record '_erp-challenge.{$tenantDomain->domain}' not detected or mismatched.",
            'data' => [
                'expected_instructions' => $this->domainService->getDnsInstructions($tenantDomain),
            ],
        ], 422);
    }

    /**
     * Set domain as primary for the school.
     */
    public function setPrimary(string $id): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $tenantDomain = TenantDomain::where('tenant_id', $tenantId)->findOrFail($id);

        if (!$tenantDomain->is_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Only verified domains can be designated as the primary domain.',
            ], 422);
        }

        // Unset any existing primary
        TenantDomain::where('tenant_id', $tenantId)->update(['is_primary' => false]);
        $tenantDomain->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => "'{$tenantDomain->domain}' is now the primary domain.",
            'data' => $tenantDomain,
        ]);
    }

    /**
     * Remove custom domain.
     */
    public function destroy(string $id): JsonResponse
    {
        $tenantId = $this->tenantContext->getTenantId() ?: auth()->user()->memberships()->value('tenant_id');
        $tenantDomain = TenantDomain::where('tenant_id', $tenantId)->findOrFail($id);

        $tenantDomain->delete();

        return response()->json([
            'success' => true,
            'message' => 'Custom domain removed successfully.',
        ]);
    }
}

