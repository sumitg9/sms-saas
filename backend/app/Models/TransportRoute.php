<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportDriver::class, 'driver_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TransportStop::class, 'route_id')->orderBy('stop_order');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(StudentTransportAllocation::class, 'route_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(TransportAttendance::class, 'route_id');
    }
}
