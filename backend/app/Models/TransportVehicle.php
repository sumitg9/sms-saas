<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportVehicle extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'transport_vehicles';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'registration_number',
        'vehicle_model',
        'capacity',
        'fitness_certificate_expiry',
        'insurance_expiry',
        'gps_device_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fitness_certificate_expiry' => 'date',
            'insurance_expiry' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TransportRoute::class, 'vehicle_id');
    }
}

