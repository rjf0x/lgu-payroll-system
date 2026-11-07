<?php

namespace Database\Factories;

use App\Models\ContributionRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContributionRate>
 */
class ContributionRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contribution_type' => ContributionRate::TYPE_PHILHEALTH, 'minimum_compensation' => 10000, 'maximum_compensation' => 100000,
            'employee_rate' => 0.025, 'employer_rate' => 0.025, 'employee_fixed_amount' => 0, 'employer_fixed_amount' => 0,
            'effective_from' => '2024-01-01', 'effective_to' => null,
        ];
    }
}
