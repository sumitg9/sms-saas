<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportDriver extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'transport_drivers';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'license_number',
        'license_expiry',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TransportRoute::class, 'driver_id');
    }
}

