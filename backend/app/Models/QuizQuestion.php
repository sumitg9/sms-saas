<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'quiz_questions';

    protected $fillable = [
        'tenant_id',
        'quiz_id',
        'question_text',
        'question_type',
        'marks',
        'options',
        'correct_answer',
        'explanation',
        'order_number',
    ];

    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
            'options' => 'array',
            'order_number' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }
}

