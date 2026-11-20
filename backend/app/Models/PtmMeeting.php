<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtmMeeting extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'ptm_meetings';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'teacher_id',
        'guardian_id',
        'student_id',
        'title',
        'meeting_type',
        'meeting_date',
        'start_time',
        'end_time',
        'room_number',
        'meeting_link',
        'status',
        'requested_by',
        'parent_notes',
        'teacher_notes',
        'feedback',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'rating' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}

