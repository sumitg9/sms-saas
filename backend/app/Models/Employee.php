<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes, TenantScoped;

    protected $table = 'employees';

    protected $fillable = [
        'tenant_id',
        'school_id',
        'branch_id',
        'user_id',
        'employee_code',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'blood_group',
        'email',
        'phone',
        'department_id',
        'designation_id',
        'employment_type',
        'date_of_joining',
        'date_of_leaving',
        'basic_salary',
        'pan_number',
        'bank_account_number',
        'bank_ifsc_code',
        'bank_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_joining' => 'date',
            'date_of_leaving' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name}");
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(EmployeeDepartment::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(EmployeeDesignation::class, 'designation_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class, 'employee_id');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class, 'employee_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class, 'employee_id');
    }

    public function teacherSubjects(): HasMany
    {
        return $this->hasMany(TeacherSubject::class, 'employee_id');
    }

    public function classTeachers(): HasMany
    {
        return $this->hasMany(ClassTeacher::class, 'employee_id');
    }
}

