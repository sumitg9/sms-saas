<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'organization_type',
        'contact_email',
        'contact_phone',
        'billing_address',
        'currency',
        'locale',
        'timezone',
        'status',
        'trial_ends_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'billing_address' => 'array',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function schools(): HasMany
    {
        return $this->hasMany(School::class, 'tenant_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(SchoolBranch::class, 'tenant_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class, 'tenant_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class, 'tenant_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'tenant_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'tenant_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'tenant_id')->latestOfMany();
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'tenant_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'tenant_id');
    }
}

