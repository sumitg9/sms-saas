<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->city() . ' International Public School',
            'code' => strtoupper(Str::random(4)) . '-' . fake()->numberBetween(10, 99),
            'affiliation_number' => 'CBSE-' . fake()->numerify('######'),
            'education_board' => fake()->randomElement(['CBSE', 'ICSE', 'STATE', 'IB']),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => [
                'city' => fake()->city(),
                'state' => fake()->state(),
                'country' => 'India',
            ],
            'is_active' => true,
        ];
    }
}

