<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMedicalRecord extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'student_medical_records';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'blood_group',
        'allergies',
        'chronic_conditions',
        'medications',
        'emergency_notes',
        'insurance_policy_number',
        'doctor_name',
        'doctor_phone',
    ];

    protected function casts(): array
    {
        return [
            'allergies' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}

