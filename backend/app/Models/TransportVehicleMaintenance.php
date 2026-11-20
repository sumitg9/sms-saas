<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransportVehicleMaintenance extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'transport_vehicle_maintenances';

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'maintenance_type',
        'service_date',
        'odometer_reading',
        'cost',
        'service_center',
        'invoice_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'cost' => 'decimal:2',
            'odometer_reading' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'vehicle_id');
    }
}

