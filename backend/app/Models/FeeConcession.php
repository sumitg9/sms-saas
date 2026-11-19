<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeConcession extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'fee_concessions';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'discount_type',
        'discount_value',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentFeeAssignment::class, 'concession_id');
    }
}

