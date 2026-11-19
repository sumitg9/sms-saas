<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportRoute extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'transport_routes';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'route_name',
        'vehicle_id',
        'driver_id',
        'start_point',
        'end_point',
        'fare',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fare' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'vehicle_id');
    }
}

