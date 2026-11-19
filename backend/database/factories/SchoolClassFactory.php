<?php

namespace Database\Factories;

use App\Models\SchoolBranch;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition(): array
    {
        $grade = fake()->numberBetween(1, 12);

        return [
            'tenant_id' => fn (array $attributes) => SchoolBranch::find($attributes['branch_id'])->tenant_id ?? null,
            'school_id' => fn (array $attributes) => SchoolBranch::find($attributes['branch_id'])->school_id ?? null,
            'branch_id' => SchoolBranch::factory(),
            'name' => "Grade {$grade}",
            'code' => "G{$grade}-" . fake()->unique()->numerify('###'),
            'order_index' => $grade,
            'is_active' => true,
        ];
    }
}

