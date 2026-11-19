<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company() . ' Educational Trust';

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'organization_type' => fake()->randomElement(['trust', 'society', 'private', 'chain']),
            'contact_email' => fake()->unique()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'billing_address' => [
                'line1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->state(),
                'country' => 'India',
                'postal_code' => fake()->postcode(),
            ],
            'currency' => 'INR',
            'locale' => 'en',
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'trial_ends_at' => null,
        ];
    }

    public function inTrial(): static
    {
        return $this->state(fn () => [
            'status' => 'in_trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}

