<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCardTemplate extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'report_card_templates';

    protected $fillable = [
        'tenant_id',
        'school_id',
        'name',
        'template_schema',
        'header_html',
        'footer_html',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'template_schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class, 'template_id');
    }
}

