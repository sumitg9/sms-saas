<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeRange extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'grade_ranges';

    protected $fillable = [
        'grading_scheme_id',
        'grade_name',
        'min_percentage',
        'max_percentage',
        'grade_point',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'min_percentage' => 'decimal:2',
            'max_percentage' => 'decimal:2',
            'grade_point' => 'decimal:2',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(GradingScheme::class, 'grading_scheme_id');
    }
}

