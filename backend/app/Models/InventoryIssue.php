<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryIssue extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'inventory_issues';

    protected $fillable = [
        'tenant_id',
        'item_id',
        'issued_to_type', // department, employee
        'issued_to_id',
        'quantity',
        'issue_date',
        'return_date',
        'status', // issued, returned, consumed
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'return_date' => 'date',
            'quantity' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function getIssuedToAttribute()
    {
        if ($this->issued_to_type === 'department') {
            return EmployeeDepartment::withoutGlobalScopes()->find($this->issued_to_id);
        }

        return Employee::withoutGlobalScopes()->find($this->issued_to_id);
    }
}

