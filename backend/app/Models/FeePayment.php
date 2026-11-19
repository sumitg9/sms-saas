<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeePayment extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'fee_payments';

    // IMMUTABLE FINANCIAL RECORD - NEVER SOFT DELETED
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'student_id',
        'receipt_number',
        'amount_paid',
        'payment_date',
        'payment_method',
        'reference_number',
        'cheque_date',
        'bank_name',
        'collected_by',
        'status',
        'receipt_pdf_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'payment_date' => 'datetime',
            'cheque_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class, 'fee_payment_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(FeeRefund::class, 'fee_payment_id');
    }
}

