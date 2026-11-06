<?php

namespace Database\Factories;

use App\Models\CompensationProfile;
use App\Models\EmploymentRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompensationProfile>
 */
class CompensationProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employment_id' => EmploymentRecord::factory(),
            'grade_step_id' => null,
            'pay_basis' => CompensationProfile::BASIS_MONTHLY,
            'basic_rate' => fake()->numberBetween(15000, 120000),
            'service_fraction' => 1.0000,
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
        ];
    }
}
