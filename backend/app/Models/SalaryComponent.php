<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryComponent extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'salary_components';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'code',
        'type', // allowance, deduction
        'calculation_type', // fixed, percentage
        'percentage_of', // BASIC, GROSS
        'default_amount',
        'is_taxable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'decimal:2',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function structureItems(): HasMany
    {
        return $this->hasMany(SalaryStructureItem::class, 'salary_component_id');
    }
}

