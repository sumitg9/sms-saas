<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\SchoolBranch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SchoolBranch>
 */
class SchoolBranchFactory extends Factory
{
    protected $model = SchoolBranch::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => School::find($attributes['school_id'])->tenant_id ?? null,
            'school_id' => School::factory(),
            'name' => fake()->streetName() . ' Campus',
            'branch_code' => 'BR-' . strtoupper(Str::random(3)) . '-' . fake()->numberBetween(1, 9),
            'campus_type' => 'main',
            'address' => [
                'city' => fake()->city(),
                'state' => fake()->state(),
            ],
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'is_active' => true,
        ];
    }
}

