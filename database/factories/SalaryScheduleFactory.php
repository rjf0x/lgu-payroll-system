<?php

namespace Database\Factories;

use App\Models\SalarySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalarySchedule>
 */
class SalaryScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schedule_name' => 'Salary Schedule '.fake()->unique()->year(),
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
            'reference_document' => fake()->optional()->bothify('LBC-###'),
            'status' => SalarySchedule::STATUS_ACTIVE,
        ];
    }
}
