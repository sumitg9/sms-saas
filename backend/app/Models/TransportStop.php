<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportStop extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'transport_stops';

    protected $fillable = [
        'tenant_id',
        'route_id',
        'stop_name',
        'pickup_time',
        'drop_time',
        'fare_amount',
        'stop_order',
    ];

    protected function casts(): array
    {
        return [
            'fare_amount' => 'decimal:2',
            'stop_order' => 'integer',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(StudentTransportAllocation::class, 'stop_id');
    }
}

