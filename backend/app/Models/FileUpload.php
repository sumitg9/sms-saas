<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileUpload extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'file_uploads';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'bucket_name',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'is_private',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'file_size' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

