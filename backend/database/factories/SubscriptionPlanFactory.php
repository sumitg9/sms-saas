<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Starter Tier', 'Growth Tier', 'Enterprise Tier']);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'description' => fake()->sentence(),
            'monthly_price' => fake()->randomElement([2999.00, 7999.00, 19999.00]),
            'annual_price' => fake()->randomElement([29990.00, 79990.00, 199990.00]),
            'currency' => 'INR',
            'max_students' => fake()->randomElement([300, 1500, -1]),
            'max_staff' => fake()->randomElement([50, 150, 500]),
            'max_branches' => fake()->randomElement([1, 3, 10]),
            'storage_limit_gb' => fake()->randomElement([10, 50, 200]),
            'is_active' => true,
        ];
    }
}

