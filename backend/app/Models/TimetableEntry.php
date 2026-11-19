<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableEntry extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'timetable_entries';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'timetable_id',
        'period_id',
        'class_id',
        'section_id',
        'subject_id',
        'employee_id',
        'day_of_week',
        'room_number',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class, 'timetable_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(TimetablePeriod::class, 'period_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(TimetableSubstitution::class, 'timetable_entry_id');
    }
}

