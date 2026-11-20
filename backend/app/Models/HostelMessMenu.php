<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostelMessMenu extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'hostel_mess_menus';

    protected $fillable = [
        'tenant_id',
        'building_id',
        'day_of_week', // monday, tuesday, etc.
        'meal_type', // breakfast, lunch, snacks, dinner
        'menu_items', // jsonb array
        'special_notes',
    ];

    protected function casts(): array
    {
        return [
            'menu_items' => 'array',
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(HostelBuilding::class, 'building_id');
    }
}

