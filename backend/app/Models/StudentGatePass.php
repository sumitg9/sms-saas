<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGatePass extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'student_gate_passes';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'student_id',
        'pass_number',
        'guardian_id',
        'reason',
        'approved_by',
        'issued_time',
        'actual_exit_time',
        'status', // approved, exited, rejected
    ];

    protected function casts(): array
    {
        return [
            'issued_time' => 'datetime',
            'actual_exit_time' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

