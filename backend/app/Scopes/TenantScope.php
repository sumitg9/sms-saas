<?php

namespace App\Scopes;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantContext = app(TenantContext::class);

        if ($tenantContext->shouldIgnoreTenantScope()) {
            return;
        }

        $tenantId = $tenantContext->getTenantId();

        if ($tenantId) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
        }
    }
}
