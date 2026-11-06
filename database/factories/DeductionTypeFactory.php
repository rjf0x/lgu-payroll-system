<?php

namespace Database\Factories;

use App\Models\DeductionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeductionType>
 */
class DeductionTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'deduction_name' => fake()->unique()->words(2, true),
            'deduction_category' => fake()->randomElement(['LOAN', 'CONTRIBUTION', 'DUES']),
            'computation_type' => fake()->randomElement(['FIXED', 'MANUAL']),
            'is_statutory' => fake()->boolean(),
            'is_pre_tax' => false,
            'status' => 'ACTIVE',
        ];
    }
}
