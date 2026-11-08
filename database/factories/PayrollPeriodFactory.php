<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth()->addMonths(fake()->unique()->numberBetween(0, 2400));

        return [
            'period_name' => $start->format('F Y'),
            'payroll_type' => PayrollPeriod::TYPE_MONTHLY,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->endOfMonth()->toDateString(),
            'pay_date' => $start->copy()->endOfMonth()->toDateString(),
            'status' => 'OPEN',
        ];
    }
}
