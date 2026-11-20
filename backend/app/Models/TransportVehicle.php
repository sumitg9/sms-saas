<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            'capacity' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(TransportRoute::class, 'vehicle_id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(TransportVehicleMaintenance::class, 'vehicle_id');
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(TransportFuelLog::class, 'vehicle_id');
    }

    public function telemetries(): HasMany
    {
        return $this->hasMany(TransportGpsTelemetry::class, 'vehicle_id');
    }

    public function isFitnessCertificateExpired(): bool
    {
        return $this->fitness_certificate_expiry ? Carbon::parse($this->fitness_certificate_expiry)->isPast() : false;
    }

    public function isInsuranceExpired(): bool
    {
        return $this->insurance_expiry ? Carbon::parse($this->insurance_expiry)->isPast() : false;
    }

    public function isDocumentExpired(): bool
    {
        return $this->isFitnessCertificateExpired() || $this->isInsuranceExpired();
    }
}
