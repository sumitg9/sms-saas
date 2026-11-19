<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageRecord extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'usage_records';

    protected $fillable = [
        'tenant_id',
        'metric_name',
        'current_value',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}

