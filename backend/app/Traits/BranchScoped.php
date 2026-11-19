<?php

namespace App\Traits;

use App\Scopes\BranchScope;
use App\Services\Tenancy\TenantContext;

trait BranchScoped
{
    /**
     * Boot the branch scoped trait for a model.
     */
    protected static function bootBranchScoped(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            $branchId = app(TenantContext::class)->getBranchId();

            if ($branchId && empty($model->branch_id)) {
                $model->branch_id = $branchId;
            }
        });
    }
}

