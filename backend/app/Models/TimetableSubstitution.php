<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSubstitution extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'timetable_substitutions';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'timetable_entry_id',
        'original_employee_id',
        'substitute_employee_id',
        'date',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TimetableEntry::class, 'timetable_entry_id');
    }

    public function originalTeacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'original_employee_id');
    }

    public function substituteTeacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'substitute_employee_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }
}

