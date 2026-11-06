<?php

namespace Database\Factories;

use App\Models\EarningType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EarningType>
 */
class EarningTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'earning_name' => fake()->unique()->words(2, true),
            'earning_category' => fake()->randomElement(['ALLOWANCE', 'BENEFIT', 'PREMIUM']),
            'computation_type' => fake()->randomElement(['FIXED', 'MANUAL']),
            'is_taxable' => fake()->boolean(),
            'is_recurring' => true,
            'status' => 'ACTIVE',
        ];
    }
}
