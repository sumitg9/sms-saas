<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaminationType extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'examination_types';

    protected $fillable = [
        'tenant_id',
        'school_id',
        'name',
        'weightage_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weightage_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function examinations(): HasMany
    {
        return $this->hasMany(Examination::class, 'exam_type_id');
    }
}

