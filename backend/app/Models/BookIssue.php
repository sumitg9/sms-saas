<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookIssue extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'book_issues';

    protected $fillable = [
        'tenant_id',
        'copy_id',
        'member_type',
        'member_id',
        'issue_date',
        'due_date',
        'return_date',
        'fine_amount',
        'fine_paid',
        'issued_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'return_date' => 'date',
            'fine_amount' => 'decimal:2',
            'fine_paid' => 'boolean',
        ];
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'copy_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}

