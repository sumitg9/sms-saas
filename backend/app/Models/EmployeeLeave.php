<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'employee_leaves';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'academic_session_id',
        'leave_type',
        'total_days',
        'used_days',
        'balance_days',
    ];

    protected function casts(): array
    {
        return [
            'total_days' => 'decimal:1',
            'used_days' => 'decimal:1',
            'balance_days' => 'decimal:1',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }
}

