<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationPreference extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'communication_preferences';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'email_enabled',
        'sms_enabled',
        'whatsapp_enabled',
        'push_enabled',
        'emergency_override',
        'dnd_start_time',
        'dnd_end_time',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'emergency_override' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

