<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentComponent extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'assessment_components';

    protected $fillable = [
        'tenant_id',
        'exam_schedule_id',
        'component_name',
        'max_marks',
        'pass_marks',
    ];

    protected function casts(): array
    {
        return [
            'max_marks' => 'decimal:2',
            'pass_marks' => 'decimal:2',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id');
    }

    public function studentAssessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'component_id');
    }
}

