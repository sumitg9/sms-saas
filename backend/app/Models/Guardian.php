<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use HasFactory, SoftDeletes, TenantScoped;

    protected $table = 'guardians';

    protected $appends = ['full_name'];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'first_name',
        'last_name',
        'relation_type',
        'phone',
        'email',
        'occupation',
        'annual_income',
        'national_id',
        'address',
    ];

    protected function casts(): array
    {
        return [
            'annual_income' => 'decimal:2',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians', 'guardian_id', 'student_id')
            ->withPivot('is_primary', 'has_pickup_rights', 'receives_invoices', 'emergency_contact_priority');
    }
}

