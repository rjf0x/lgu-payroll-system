<?php

namespace Database\Factories;

use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryGradeStep>
 */
class SalaryGradeStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salary_schedule_id' => SalarySchedule::factory(),
            'grade_number' => fake()->numberBetween(1, 33),
            'step_number' => fake()->numberBetween(1, 8),
            'monthly_salary' => fake()->numberBetween(15000, 120000),
        ];
    }
}
