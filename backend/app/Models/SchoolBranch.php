<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolBranch extends Model
{
    use HasFactory, SoftDeletes, TenantScoped;

    protected $table = 'school_branches';

    protected $fillable = [
        'tenant_id',
        'school_id',
        'name',
        'branch_code',
        'campus_type',
        'address',
        'phone',
        'email',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function academicSessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class, 'branch_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'branch_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'branch_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'branch_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }
}

