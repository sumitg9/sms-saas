<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryVendor extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'inventory_vendors';

    protected $fillable = [
        'tenant_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'gst_number',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(InventoryPurchase::class, 'vendor_id');
    }
}

