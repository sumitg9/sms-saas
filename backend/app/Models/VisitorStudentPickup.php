<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorStudentPickup extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'visitor_student_pickups';

    protected $fillable = [
        'tenant_id',
        'visitor_pass_id',
        'student_id',
        'guardian_id',
        'authorization_status', // verified, pending, rejected
        'relationship_to_student',
        'parent_consent_confirmed',
        'parent_contact_phone',
        'verification_notes',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'parent_consent_confirmed' => 'boolean',
        ];
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(VisitorPass::class, 'visitor_pass_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

