<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultPublication extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'result_publications';

    protected $fillable = [
        'tenant_id',
        'examination_id',
        'class_id',
        'published_by',
        'published_at',
        'allow_parent_view',
        'allow_student_view',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'allow_parent_view' => 'boolean',
            'allow_student_view' => 'boolean',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class, 'examination_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}

