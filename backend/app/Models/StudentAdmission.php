<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAdmission extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'student_admissions';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'academic_session_id',
        'student_id',
        'application_number',
        'applicant_first_name',
        'applicant_last_name',
        'applied_class_id',
        'date_of_application',
        'status',
        'application_fee_paid',
        'inquiry_details',
    ];

    protected function casts(): array
    {
        return [
            'date_of_application' => 'date',
            'application_fee_paid' => 'boolean',
            'inquiry_details' => 'array',
        ];
    }

    public function appliedClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'applied_class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function getApplicantFullNameAttribute(): string
    {
        return trim("{$this->applicant_first_name} {$this->applicant_last_name}");
    }
}

