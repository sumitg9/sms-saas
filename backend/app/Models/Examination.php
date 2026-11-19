<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Examination extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'examinations';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'academic_session_id',
        'exam_type_id',
        'grading_scheme_id',
        'title',
        'start_date',
        'end_date',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExaminationType::class, 'exam_type_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class, 'examination_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ExamResult::class, 'examination_id');
    }
}

