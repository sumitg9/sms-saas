<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeInvoice extends Model
{
    use HasFactory, SoftDeletes, TenantScoped;

    protected $table = 'fee_invoices';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'student_id',
        'enrollment_id',
        'fee_assignment_id',
        'academic_session_id',
        'invoice_number',
        'subtotal',
        'discount_amount',
        'fine_amount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'due_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'fine_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeInvoiceItem::class, 'fee_invoice_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class, 'fee_invoice_id');
    }

    public function fines(): HasMany
    {
        return $this->hasMany(FeeFine::class, 'fee_invoice_id');
    }
}

