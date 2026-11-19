<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait TenantScoped
{
    use HasUuids;

    /**
     * Boot the tenant scoped trait for a model.
     */
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $tenantId = app(TenantContext::class)->getTenantId();

            if ($tenantId && empty($model->tenant_id)) {
                $model->tenant_id = $tenantId;
            }
        });
    }
}
