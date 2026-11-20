<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'notification_templates';

    protected $fillable = [
        'tenant_id',
        'event_name',
        'channel',
        'language',
        'subject',
        'body_template',
        'placeholders',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'is_active' => 'boolean',
        ];
    }
}

