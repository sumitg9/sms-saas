<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeFine extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'fee_fines';

    protected $fillable = [
        'tenant_id',
        'fee_invoice_id',
        'fine_amount',
        'reason',
        'calculation_date',
        'is_waived',
        'waived_by',
        'waiver_reason',
    ];

    protected function casts(): array
    {
        return [
            'fine_amount' => 'decimal:2',
            'calculation_date' => 'date',
            'is_waived' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function waiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }
}

