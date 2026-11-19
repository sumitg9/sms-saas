<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'payslips';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'payroll_period_id',
        'employee_id',
        'payslip_number',
        'basic_salary',
        'total_allowances',
        'total_deductions',
        'gross_salary',
        'net_salary',
        'working_days',
        'present_days',
        'paid_leaves',
        'unpaid_leaves',
        'earnings_breakdown',
        'deductions_breakdown',
        'status', // draft, published, paid
        'payment_method',
        'payment_reference',
        'payment_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'total_allowances' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'working_days' => 'integer',
            'present_days' => 'decimal:1',
            'paid_leaves' => 'decimal:1',
            'unpaid_leaves' => 'decimal:1',
            'earnings_breakdown' => 'array',
            'deductions_breakdown' => 'array',
            'payment_date' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}

