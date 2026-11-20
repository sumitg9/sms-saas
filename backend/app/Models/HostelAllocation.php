<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostelAllocation extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'hostel_allocations';

    protected $fillable = [
        'tenant_id',
        'room_id',
        'student_id',
        'enrollment_id',
        'bed_number',
        'allocation_date',
        'vacating_date',
        'status', // active, vacated
    ];

    protected function casts(): array
    {
        return [
            'allocation_date' => 'date',
            'vacating_date' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class, 'room_id');
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

