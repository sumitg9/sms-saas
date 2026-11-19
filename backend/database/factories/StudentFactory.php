<?php

namespace Database\Factories;

use App\Models\SchoolBranch;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'tenant_id' => fn (array $attributes) => SchoolBranch::find($attributes['branch_id'])->tenant_id ?? null,
            'school_id' => fn (array $attributes) => SchoolBranch::find($attributes['branch_id'])->school_id ?? null,
            'branch_id' => SchoolBranch::factory(),
            'user_id' => null,
            'admission_number' => 'ADM-' . fake()->unique()->numerify('####'),
            'admission_date' => fake()->date('Y-m-d', 'now'),
            'first_name' => fake()->firstName($gender),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-16 years', '-5 years')->format('Y-m-d'),
            'blood_group' => fake()->randomElement(['A+', 'B+', 'O+', 'AB+']),
            'nationality' => 'Indian',
            'caste_category' => 'General',
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'current_address' => fake()->address(),
            'status' => 'active',
        ];
    }
}

