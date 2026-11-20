<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostelBuilding extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'hostel_buildings';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'building_type', // boys, girls, co_ed
        'warden_name',
        'warden_phone',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HostelRoom::class, 'building_id');
    }

    public function messMenus(): HasMany
    {
        return $this->hasMany(HostelMessMenu::class, 'building_id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(HostelComplaint::class, 'building_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(HostelAttendance::class, 'building_id');
    }
}

