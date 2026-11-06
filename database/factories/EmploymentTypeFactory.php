<?php

namespace Database\Factories;

use App\Models\EmploymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentType>
 */
class EmploymentTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employment_type_name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'status' => 'ACTIVE',
        ];
    }
}
