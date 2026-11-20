<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostelRoom extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'hostel_rooms';

    protected $fillable = [
        'tenant_id',
        'building_id',
        'room_number',
        'room_type', // single, double, dormitory
        'capacity',
        'occupied_beds',
        'fee_per_session',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'occupied_beds' => 'integer',
            'fee_per_session' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(HostelBuilding::class, 'building_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(HostelAllocation::class, 'room_id');
    }

    public function activeAllocations(): HasMany
    {
        return $this->hasMany(HostelAllocation::class, 'room_id')->where('status', 'active');
    }

    public function availableBeds(): int
    {
        return max(0, $this->capacity - $this->occupied_beds);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && ($this->availableBeds() > 0);
    }

    public function syncOccupancy(): void
    {
        $count = $this->activeAllocations()->count();
        $this->update(['occupied_beds' => $count]);
    }
}

