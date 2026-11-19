<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'leave_applications';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'applicant_type',
        'applicant_id',
        'leave_type',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'medical_certificate_path',
        'status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_days' => 'decimal:1',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'applicant_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'applicant_id');
    }
}

