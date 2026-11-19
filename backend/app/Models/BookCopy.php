<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookCopy extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'book_copies';

    protected $fillable = [
        'tenant_id',
        'book_id',
        'barcode',
        'copy_number',
        'status',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(LibraryBook::class, 'book_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(BookIssue::class, 'copy_id');
    }
}

