<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportFuelLog extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'transport_fuel_logs';

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'fuel_date',
        'fuel_type',
        'quantity_litres',
        'cost_per_litre',
        'total_cost',
        'odometer_reading',
        'receipt_number',
    ];

    protected function casts(): array
    {
        return [
            'fuel_date' => 'date',
            'quantity_litres' => 'decimal:2',
            'cost_per_litre' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'odometer_reading' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'vehicle_id');
    }
}

