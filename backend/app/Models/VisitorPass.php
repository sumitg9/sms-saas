<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisitorPass extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'visitor_passes';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'visitor_id',
        'pass_number',
        'host_employee_id',
        'purpose',
        'check_in_time',
        'check_out_time',
        'badge_printed',
        'status', // checked_in, checked_out
    ];

    protected function casts(): array
    {
        return [
            'check_in_time' => 'datetime',
            'check_out_time' => 'datetime',
            'badge_printed' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class, 'visitor_id');
    }

    public function hostEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'host_employee_id');
    }

    public function pickup(): HasOne
    {
        return $this->hasOne(VisitorStudentPickup::class, 'visitor_pass_id');
    }
}

