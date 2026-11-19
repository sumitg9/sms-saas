<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeCategory extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'fee_categories';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class, 'fee_category_id');
    }
}

