<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTransfer extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'student_transfers';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'student_id',
        'tc_number',
        'application_date',
        'issue_date',
        'destination_school',
        'reason_for_leaving',
        'clearance_status',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
            'issue_date' => 'date',
            'clearance_status' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }
}
