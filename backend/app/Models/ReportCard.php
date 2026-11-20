<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCard extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'report_cards';

    protected $fillable = [
        'tenant_id',
        'examination_id',
        'student_id',
        'enrollment_id',
        'template_id',
        'generated_pdf_path',
        'is_signed',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_signed' => 'boolean',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportCardTemplate::class, 'template_id');
    }
}

