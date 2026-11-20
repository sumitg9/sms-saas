<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Carbon\Carbon;
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
        'member_type', // student, employee
        'member_id',
        'issue_date',
        'due_date',
        'return_date',
        'fine_amount',
        'fine_paid',
        'issued_by',
        'status', // issued, returned, overdue
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

    public function getMemberAttribute()
    {
        if ($this->member_type === 'student') {
            return Student::withoutGlobalScopes()->find($this->member_id);
        }

        return Employee::withoutGlobalScopes()->find($this->member_id);
    }

    public function isOverdue(): bool
    {
        if ($this->status === 'returned') {
            return false;
        }

        return Carbon::parse($this->due_date)->isPast();
    }

    public function calculateOverdueFine(float $fineRatePerDay = 5.0): float
    {
        $targetDate = $this->return_date ? Carbon::parse($this->return_date) : Carbon::today();
        $dueDate = Carbon::parse($this->due_date);

        if ($targetDate->greaterThan($dueDate)) {
            $daysLate = abs((int) $targetDate->diffInDays($dueDate));
            return round($daysLate * $fineRatePerDay, 2);
        }

        return 0.00;
    }
}
