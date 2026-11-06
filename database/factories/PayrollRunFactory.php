<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'run_number' => 1,
            'run_type' => 'REGULAR',
            'status' => PayrollRun::STATUS_DRAFT,
            'created_by' => User::factory(),
            'processed_at' => null,
            'submitted_by' => null,
            'submitted_at' => null,
            'finalized_by' => null,
            'finalized_at' => null,
        ];
    }
}
