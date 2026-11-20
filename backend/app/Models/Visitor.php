<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visitor extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'visitors';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'full_name',
        'phone',
        'national_id',
        'photo_url',
        'address',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SchoolBranch::class, 'branch_id');
    }

    public function passes(): HasMany
    {
        return $this->hasMany(VisitorPass::class, 'visitor_id')->orderByDesc('check_in_time');
    }

    public function activePass(): HasOne
    {
        return $this->hasOne(VisitorPass::class, 'visitor_id')
            ->where('status', 'checked_in')
            ->orderByDesc('check_in_time');
    }
}

