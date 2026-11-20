<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultCorrection extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'result_corrections';

    protected $fillable = [
        'tenant_id',
        'student_marks_id',
        'old_marks',
        'new_marks',
        'reason',
        'requested_by',
        'approved_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'old_marks' => 'decimal:2',
            'new_marks' => 'decimal:2',
        ];
    }

    public function studentMark(): BelongsTo
    {
        return $this->belongsTo(StudentMark::class, 'student_marks_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

