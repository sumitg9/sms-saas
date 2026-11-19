<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'exam_results';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'examination_id',
        'student_id',
        'enrollment_id',
        'total_marks',
        'marks_obtained',
        'percentage',
        'grade',
        'grade_point',
        'rank',
        'result_status',
    ];

    protected function casts(): array
    {
        return [
            'total_marks' => 'decimal:2',
            'marks_obtained' => 'decimal:2',
            'percentage' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'rank' => 'integer',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class, 'examination_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'enrollment_id');
    }
}

