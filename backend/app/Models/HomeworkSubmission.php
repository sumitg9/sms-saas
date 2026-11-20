<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkSubmission extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'homework_submissions';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'homework_id',
        'student_id',
        'submission_text',
        'attachments',
        'submitted_at',
        'is_late',
        'status',
        'marks_obtained',
        'feedback',
        'graded_by',
        'graded_at',
        'resubmission_count',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
            'marks_obtained' => 'decimal:2',
            'resubmission_count' => 'integer',
        ];
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class, 'homework_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}

