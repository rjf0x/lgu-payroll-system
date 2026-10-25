<?php

namespace Database\Factories;

use App\Enums\PersonnelClassification;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_number' => 'DEMO-'.strtoupper(fake()->unique()->bothify('????-########')),
            'first_name' => 'Fictional '.fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'position' => 'Demonstration Accounting Clerk',
            'office' => 'Fictional Accounting Office',
            'personnel_classification' => fake()->randomElement(PersonnelClassification::cases()),
            'employment_start_date' => '2024-01-01',
            'is_active' => true,
        ];
    }
}
