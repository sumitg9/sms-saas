<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'announcements';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'title',
        'content',
        'target_audience',
        'class_id',
        'section_id',
        'priority',
        'published_at',
        'expires_at',
        'author_id',
        'is_published',
        'attachments',
        'requires_acknowledgement',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_published' => 'boolean',
            'requires_acknowledgement' => 'boolean',
            'attachments' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function acknowledgements(): HasMany
    {
        return $this->hasMany(AnnouncementAcknowledgement::class, 'announcement_id');
    }
}
