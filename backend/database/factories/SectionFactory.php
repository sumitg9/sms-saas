<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => SchoolClass::find($attributes['class_id'])->tenant_id ?? null,
            'branch_id' => fn (array $attributes) => SchoolClass::find($attributes['class_id'])->branch_id ?? null,
            'class_id' => SchoolClass::factory(),
            'name' => 'Section ' . fake()->randomElement(['A', 'B', 'C', 'D']),
            'room_number' => 'R-' . fake()->numberBetween(101, 305),
            'max_capacity' => 40,
            'is_active' => true,
        ];
    }
}

