<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'inventory_items';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'category_id',
        'item_name',
        'item_code',
        'unit_of_measure',
        'min_stock_alert',
        'current_stock',
    ];

    protected function casts(): array
    {
        return [
            'min_stock_alert' => 'integer',
            'current_stock' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(InventoryIssue::class, 'item_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryStockTransaction::class, 'item_id')->orderByDesc('created_at');
    }

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->min_stock_alert;
    }
}

