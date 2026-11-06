<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_code' => 'DEPT-'.fake()->unique()->numerify('####'),
            'department_name' => fake()->unique()->company(),
            'description' => fake()->optional()->sentence(),
            'status' => 'ACTIVE',
        ];
    }
}
