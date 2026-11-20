<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAssessment extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'student_assessments';

    protected $fillable = [
        'tenant_id',
        'student_marks_id',
        'component_id',
        'marks_obtained',
    ];

    protected function casts(): array
    {
        return [
            'marks_obtained' => 'decimal:2',
        ];
    }

    public function studentMark(): BelongsTo
    {
        return $this->belongsTo(StudentMark::class, 'student_marks_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentComponent::class, 'component_id');
    }
}

