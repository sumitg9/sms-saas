<?php

namespace App\Scopes;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BranchScope implements Scope
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

        $branchId = $tenantContext->getBranchId();

        if ($branchId) {
            $builder->where($model->qualifyColumn('branch_id'), $branchId);
        }
    }
}

