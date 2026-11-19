<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGuardian extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'student_guardians';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'guardian_id',
        'is_primary',
        'has_pickup_rights',
        'receives_invoices',
        'emergency_contact_priority',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'has_pickup_rights' => 'boolean',
            'receives_invoices' => 'boolean',
            'emergency_contact_priority' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }
}

