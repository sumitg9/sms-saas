<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeDepartment extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'employee_departments';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function designations(): HasMany
    {
        return $this->hasMany(EmployeeDesignation::class, 'department_id');
    }
}

