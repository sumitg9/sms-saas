<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryBook extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'library_books';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'title',
        'isbn',
        'author',
        'publisher',
        'edition',
        'category',
        'total_copies',
        'available_copies',
        'shelf_no',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class, 'book_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(BookReservation::class, 'book_id');
    }

    public function syncCounts(): void
    {
        $total = $this->copies()->count();
        $available = $this->copies()->where('status', 'available')->count();

        $this->update([
            'total_copies' => $total,
            'available_copies' => $available,
        ]);
    }
}
