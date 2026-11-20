<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'export_jobs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'export_type', // students, fee_defaulters, payroll, report_cards, etc.
        'format', // xlsx, csv, pdf
        'file_path',
        'status', // pending, processing, completed, failed
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}

