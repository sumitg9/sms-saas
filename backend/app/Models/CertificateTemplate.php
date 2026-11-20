<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'certificate_templates';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'type', // transfer, bonafide, character, study, fee_clearance, custom
        'title',
        'body_template',
        'header_content',
        'footer_content',
        'logo_path',
        'signature_title_1',
        'signature_title_2',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }
}

