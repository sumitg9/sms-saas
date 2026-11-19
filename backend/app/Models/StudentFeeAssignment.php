<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentFeeAssignment extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'student_fee_assignments';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'student_id',
        'enrollment_id',
        'fee_structure_id',
        'concession_id',
        'gross_amount',
        'discount_amount',
        'net_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
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

    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    public function concession(): BelongsTo
    {
        return $this->belongsTo(FeeConcession::class, 'concession_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class, 'fee_assignment_id');
    }
}

