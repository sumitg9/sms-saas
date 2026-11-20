<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes, TenantScoped;

    protected $table = 'students';

    protected $appends = ['full_name'];

    protected $fillable = [
        'tenant_id',
        'school_id',
        'branch_id',
        'user_id',
        'admission_number',
        'admission_date',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'blood_group',
        'nationality',
        'religion',
        'caste_category',
        'national_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo_url',
        'current_address',
        'permanent_address',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'admission_date' => 'date',
            'date_of_birth' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return preg_replace('/\s+/', ' ', trim("{$this->first_name} {$this->middle_name} {$this->last_name}"));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Historical enrollments preserved!
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class, 'student_id');
    }

    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class, 'student_id')
            ->orderByDesc('enrollment_date')
            ->orderByDesc('created_at');
    }

    public function activeEnrollment(): HasOne
    {
        return $this->currentEnrollment();
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians', 'student_id', 'guardian_id')
            ->withPivot('is_primary', 'has_pickup_rights', 'receives_invoices', 'emergency_contact_priority');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class, 'student_id');
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(StudentMedicalRecord::class, 'student_id');
    }

    public function feeAssignments(): HasMany
    {
        return $this->hasMany(StudentFeeAssignment::class, 'student_id');
    }

    public function feeInvoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class, 'student_id');
    }

    public function feePayments(): HasMany
    {
        return $this->hasMany(FeePayment::class, 'student_id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class, 'student_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(StudentTransfer::class, 'student_id');
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(StudentAdmission::class, 'student_id');
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
    }
}

