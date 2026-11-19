<?php

namespace Database\Factories;

use App\Models\Guardian;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'relation_type' => fake()->randomElement(['father', 'mother', 'legal_guardian']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'occupation' => fake()->jobTitle(),
            'annual_income' => fake()->randomFloat(2, 300000, 2500000),
            'address' => fake()->address(),
        ];
    }
}

