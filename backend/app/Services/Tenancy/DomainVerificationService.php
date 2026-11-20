<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DomainVerificationService
{
    /**
     * Reserved subdomains and hostnames that cannot be registered as custom domains.
     */
    protected array $reservedDomains = [
        'localhost',
        '127.0.0.1',
        'api',
        'app',
        'platform',
        'admin',
        'mail',
        'www',
        'staging',
        'dev',
    ];

    /**
     * Register a new custom domain for a school tenant.
     */
    public function registerDomain(Tenant $tenant, string $domain): TenantDomain
    {
        $normalizedDomain = strtolower(trim(preg_replace('#^https?://#', '', $domain), '/'));

        // Validate FQDN format
        if (!preg_match('/^(?!:\/\/)([a-zA-Z0-9-_]+\.)+[a-zA-Z]{2,}$/', $normalizedDomain)) {
            throw ValidationException::withMessages([
                'domain' => ['Please enter a valid Fully Qualified Domain Name (e.g. portal.myschool.edu).'],
            ]);
        }

        // Check reserved domains
        foreach ($this->reservedDomains as $reserved) {
            if ($normalizedDomain === $reserved || Str::startsWith($normalizedDomain, "{$reserved}.")) {
                throw ValidationException::withMessages([
                    'domain' => ["The domain '{$normalizedDomain}' is reserved for platform infrastructure."],
                ]);
            }
        }

        // Check if already claimed by any tenant
        $exists = TenantDomain::withoutGlobalScopes()
            ->where('domain', $normalizedDomain)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'domain' => ["The domain '{$normalizedDomain}' is already claimed by another institution."],
            ]);
        }

        $verificationToken = 'erp-verify-' . Str::random(32);

        return TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $normalizedDomain,
            'is_primary' => false,
            'is_verified' => false,
            'verification_token' => $verificationToken,
            'ssl_status' => 'pending_dns',
        ]);
    }

    /**
     * Verify ownership of a custom domain via DNS TXT record check.
     */
    public function verifyDomainOwnership(TenantDomain $tenantDomain): bool
    {
        if ($tenantDomain->is_verified) {
            return true;
        }

        $hostToCheck = "_erp-challenge." . $tenantDomain->domain;
        $expectedToken = $tenantDomain->verification_token;

        $verified = false;

        // Perform DNS lookup if networking is enabled or fallback in test/mock environment
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($hostToCheck, DNS_TXT);
            if (!empty($records)) {
                foreach ($records as $record) {
                    if (isset($record['txt']) && trim($record['txt']) === $expectedToken) {
                        $verified = true;
                        break;
                    }
                }
            }
        }

        // In test environments or when explicitly simulated via header
        if (!$verified && app()->environment('local', 'testing')) {
            // Check if mock bypass is active for local verification testing
            if (request()->header('X-Simulate-DNS-Match') === 'true') {
                $verified = true;
            }
        }

        if ($verified) {
            $tenantDomain->update([
                'is_verified' => true,
                'ssl_status' => 'active',
            ]);
            return true;
        }

        return false;
    }

    /**
     * Get required DNS verification instructions for a custom domain.
     */
    public function getDnsInstructions(TenantDomain $tenantDomain): array
    {
        $cnameTarget = config('app.custom_domain_cname_target', 'custom.example.com');

        return [
            'txt_record' => [
                'type' => 'TXT',
                'host' => "_erp-challenge.{$tenantDomain->domain}",
                'value' => $tenantDomain->verification_token,
                'ttl' => 300,
                'purpose' => 'Domain Ownership Verification',
            ],
            'cname_record' => [
                'type' => 'CNAME',
                'host' => $tenantDomain->domain,
                'value' => $cnameTarget,
                'ttl' => 3600,
                'purpose' => 'Traffic Routing & SSL Termination',
            ],
        ];
    }
}

