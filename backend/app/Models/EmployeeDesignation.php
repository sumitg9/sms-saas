<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeDesignation extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'employee_designations';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'department_id',
        'title',
        'code',
        'level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'level' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(EmployeeDepartment::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'designation_id');
    }
}

