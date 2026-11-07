<?php

namespace Database\Factories;

use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollDetail>
 */
class PayrollDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'employment_id' => EmploymentRecord::factory(),
            'employee_id' => fn (array $attributes): int => EmploymentRecord::findOrFail($attributes['employment_id'])->employee_id,
            'employee_number_snapshot' => 'TEST-001', 'employee_name_snapshot' => 'Test Employee',
            'basic_pay' => 45000, 'total_earnings' => 0, 'gross_pay' => 45000, 'total_deductions' => 0,
            'nthp_before_lbp' => 45000, 'lbp_loan_deduction' => 0, 'net_pay' => 45000,
            'days_worked' => 22, 'days_present' => 22, 'days_absent' => 0, 'days_on_leave' => 0, 'payroll_status' => 'COMPUTED',
        ];
    }
}
