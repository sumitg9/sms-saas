<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetablePeriod extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'timetable_periods';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'timetable_id',
        'period_number',
        'period_name',
        'start_time',
        'end_time',
        'is_break',
    ];

    protected function casts(): array
    {
        return [
            'period_number' => 'integer',
            'is_break' => 'boolean',
        ];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class, 'timetable_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'period_id');
    }
}

