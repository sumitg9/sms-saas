<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingScheme extends Model
{
    use HasFactory, HasUuids, TenantScoped;

    protected $table = 'grading_schemes';

    protected $fillable = [
        'tenant_id',
        'school_id',
        'name',
        'scheme_type',
        'description',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function ranges(): HasMany
    {
        return $this->hasMany(GradeRange::class, 'grading_scheme_id')->orderByDesc('min_percentage');
    }
}

