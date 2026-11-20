<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostelComplaint extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'hostel_complaints';

    protected $fillable = [
        'tenant_id',
        'building_id',
        'room_id',
        'student_id',
        'title',
        'description',
        'category', // plumbing, electrical, cleanliness, food, security, other
        'priority', // low, medium, high, urgent
        'status', // pending, in_progress, resolved, closed
        'resolution_notes',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(HostelBuilding::class, 'building_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(HostelRoom::class, 'room_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

