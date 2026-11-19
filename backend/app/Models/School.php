<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasFactory, SoftDeletes, TenantScoped;

    protected $table = 'schools';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'affiliation_number',
        'education_board',
        'email',
        'phone',
        'address',
        'logo_path',
        'stamp_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(SchoolBranch::class, 'school_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'school_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'school_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'school_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'school_id');
    }
}

