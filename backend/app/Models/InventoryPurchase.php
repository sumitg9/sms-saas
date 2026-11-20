<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPurchase extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'inventory_purchases';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'vendor_id',
        'invoice_number',
        'purchase_date',
        'total_amount',
        'payment_status',
        'bill_path',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(InventoryVendor::class, 'vendor_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryStockTransaction::class, 'reference_id')
            ->where('reference_type', 'purchase');
    }
}

