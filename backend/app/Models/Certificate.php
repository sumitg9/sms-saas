<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'certificates';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'template_id',
        'student_id',
        'academic_session_id',
        'certificate_number',
        'type',
        'title',
        'rendered_content',
        'recipient_name',
        'issue_date',
        'verification_code',
        'file_path',
        'status', // issued, revoked
        'revoked_at',
        'revocation_reason',
        'revoked_by',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }
}

