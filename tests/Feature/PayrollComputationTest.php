<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\EmploymentTypeSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollComputationTest extends TestCase
{
    use RefreshDatabase;

    private float $payableDays = 22.0;

    private EmploymentRecord $record;

    private PayrollPeriod $period;

    private PayrollRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EmploymentTypeSeeder::class,
            EarningTypeSeeder::class,
            DeductionTypeSeeder::class,
            StatutoryConfigSeeder::class,
        ]);

        $department = Department::factory()->create();
        $position = Position::factory()->create([
            'department_id' => $department->department_id,
            'salary_grade' => 15,
        ]);
        $type = EmploymentType::where('employment_type_name', EmploymentType::PERMANENT)->firstOrFail();

        $this->record = EmploymentRecord::factory()->create([
            'employee_id' => Employee::factory()->create()->employee_id,
            'department_id' => $department->department_id,
            'position_id' => $position->position_id,
            'employment_type_id' => $type->employment_type_id,
        ]);

        $schedule = SalarySchedule::factory()->create([
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'status' => SalarySchedule::STATUS_ACTIVE,
        ]);
        $step = SalaryGradeStep::factory()->create([
            'salary_schedule_id' => $schedule->salary_schedule_id,
            'grade_number' => 15,
            'step_number' => 1,
            'monthly_salary' => 45000,
        ]);

        CompensationProfile::factory()->create([
            'employment_id' => $this->record->employment_id,
            'grade_step_id' => $step->grade_step_id,
            'pay_basis' => CompensationProfile::BASIS_MONTHLY,
            'basic_rate' => null,
            'effective_from' => '2024-01-01',
        ]);

        $this->period = PayrollPeriod::factory()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'pay_date' => '2025-01-31',
        ]);

        $this->run = PayrollRun::factory()->create([
            'payroll_period_id' => $this->period->payroll_period_id,
            'created_by' => User::factory()->create()->user_id,
        ]);
    }

    private function compute(): PayrollDetail
    {
        PayrollDetail::factory()->create(['payroll_run_id' => $this->run->getKey(), 'employment_id' => $this->record->getKey(), 'payable_days' => $this->payableDays]);
        app(PayrollComputationService::class)->computeRun($this->run);

        return $this->run->details()->firstOrFail();
    }

    public function test_documented_example_computes_expected_totals(): void
    {
        $detail = $this->compute();

        $this->assertSame('45000.00', (string) $detail->basic_pay);
        $this->assertSame('45000.00', (string) $detail->gross_pay);
        $this->assertSame('8508.33', (string) $detail->total_deductions);
        $this->assertSame('36491.67', (string) $detail->nthp_before_lbp);
        $this->assertSame('36491.67', (string) $detail->net_pay);

        $this->assertDatabaseHas('payroll_deductions', [
            'payroll_detail_id' => $detail->payroll_detail_id,
            'deduction_name_snapshot' => 'GSIS Contribution',
            'amount' => 4050.00,
        ]);
        $this->assertDatabaseHas('payroll_deductions', [
            'payroll_detail_id' => $detail->payroll_detail_id,
            'deduction_name_snapshot' => 'PhilHealth Contribution',
            'amount' => 1125.00,
        ]);
        $this->assertDatabaseHas('payroll_deductions', [
            'payroll_detail_id' => $detail->payroll_detail_id,
            'deduction_name_snapshot' => 'Pag-IBIG Contribution',
            'amount' => 200.00,
        ]);
        $this->assertDatabaseHas('payroll_deductions', [
            'payroll_detail_id' => $detail->payroll_detail_id,
            'deduction_name_snapshot' => 'Withholding Tax',
            'amount' => 3133.33,
        ]);
    }

    public function test_employer_contributions_are_recorded_separately(): void
    {
        $detail = $this->compute();

        $employerTotal = (float) $detail->employerContributions()->sum('amount');

        // GSIS 12% = 5400, PhilHealth 2.5% = 1125, Pag-IBIG 2% of 10k = 200.
        $this->assertSame(6725.0, $employerTotal);
    }

    public function test_approved_payable_days_reduce_basic_pay(): void
    {
        $this->payableDays = 20;

        $detail = $this->compute();

        // (22 - 2) / 22 * 45000 = 40909.09
        $this->assertSame('40909.09', (string) $detail->basic_pay);
        $this->assertSame('20.00', (string) $detail->payable_days);
    }

    public function test_daily_rate_employee_is_paid_per_credited_day(): void
    {
        $this->payableDays = 1.5;
        $this->record->compensationProfiles()->delete();
        CompensationProfile::factory()->create([
            'employment_id' => $this->record->employment_id,
            'grade_step_id' => null,
            'pay_basis' => CompensationProfile::BASIS_DAILY,
            'basic_rate' => 800,
            'service_fraction' => 1.0,
            'effective_from' => '2024-01-01',
        ]);

        $detail = $this->compute();

        // 800 * 1.5 credited days = 1200.
        $this->assertSame('1200.00', (string) $detail->basic_pay);
    }
}
