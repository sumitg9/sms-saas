<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSubject extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'exam_subjects';

    protected $fillable = [
        'tenant_id',
        'examination_id',
        'subject_id',
        'max_theory_marks',
        'max_practical_marks',
        'pass_percentage',
    ];

    protected function casts(): array
    {
        return [
            'max_theory_marks' => 'decimal:2',
            'max_practical_marks' => 'decimal:2',
            'pass_percentage' => 'decimal:2',
        ];
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class, 'examination_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}

