<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'notification_logs';

    protected $fillable = [
        'tenant_id',
        'channel',
        'recipient',
        'idempotency_key',
        'message_content',
        'status',
        'retry_count',
        'max_retries',
        'error_message',
        'gateway_response',
        'sent_at',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway_response' => 'array',
            'sent_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'retry_count' => 'integer',
            'max_retries' => 'integer',
        ];
    }
}

