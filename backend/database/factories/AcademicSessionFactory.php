<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\SchoolBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    protected $model = AcademicSession::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2025, 2028);

        return [
            'tenant_id' => fn (array $attributes) => SchoolBranch::find($attributes['branch_id'])->tenant_id ?? null,
            'school_id' => fn (array $attributes) => SchoolBranch::find($attributes['branch_id'])->school_id ?? null,
            'branch_id' => SchoolBranch::factory(),
            'name' => "{$year}-" . ($year + 1),
            'start_date' => "{$year}-04-01",
            'end_date' => ($year + 1) . "-03-31",
            'is_current' => true,
        ];
    }
}

