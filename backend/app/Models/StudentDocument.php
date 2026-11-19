<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'student_documents';

    protected $fillable = [
        'tenant_id',
        'student_id',
        'document_type',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'verification_status',
        'verified_by',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

