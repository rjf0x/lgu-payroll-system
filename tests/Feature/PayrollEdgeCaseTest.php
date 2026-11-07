<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\TaxBracket;
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private function detail(float $basicPay): PayrollDetail
    {
        $this->seed(DeductionTypeSeeder::class);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'pay_date' => '2026-01-31']);
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey()]);

        return PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'basic_pay' => $basicPay]);
    }

    public static function contributionBounds(): array
    {
        return [[5000, '250.00'], [10000, '250.00'], [100000, '2500.00'], [150000, '2500.00']];
    }

    #[DataProvider('contributionBounds')]
    public function test_contributions_apply_the_configured_floor_and_ceiling(float $salary, string $expected): void
    {
        $detail = $this->detail($salary);
        ContributionRate::factory()->create();

        app(PayrollComputationService::class)->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);

        $this->assertSame($expected, $detail->deductions()->where('deduction_name_snapshot', 'PhilHealth Contribution')->firstOrFail()->amount);
        $this->assertSame($expected, $detail->employerContributions()->firstOrFail()->amount);
    }

    public static function taxBounds(): array
    {
        return [[9999.99, null], [10000, null], [10010, '1.00'], [20000, '1000.00']];
    }

    #[DataProvider('taxBounds')]
    public function test_annual_projection_selects_boundary_brackets_and_rounds_monthly_tax(float $salary, ?string $expected): void
    {
        $detail = $this->detail($salary);
        TaxBracket::factory()->create(['minimum_income' => 0, 'maximum_income' => 120000]);
        TaxBracket::factory()->create(['minimum_income' => 120000, 'marginal_tax_rate' => 0.1]);

        app(PayrollComputationService::class)->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);

        $tax = $detail->deductions()->where('deduction_name_snapshot', 'Withholding Tax')->first();
        $this->assertSame($expected, $tax?->amount);
    }

    public function test_newer_tax_version_is_selected_instead_of_an_older_matching_bracket(): void
    {
        $detail = $this->detail(20000);
        TaxBracket::factory()->create(['marginal_tax_rate' => 0.1]);
        TaxBracket::factory()->create(['marginal_tax_rate' => 0.2, 'effective_from' => '2026-01-01']);

        app(PayrollComputationService::class)->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);

        $this->assertSame('4000.00', $detail->deductions()->where('deduction_name_snapshot', 'Withholding Tax')->firstOrFail()->amount);
    }

    public function test_expired_mid_period_rate_does_not_apply_at_the_cutoff(): void
    {
        $detail = $this->detail(20000);
        ContributionRate::factory()->create(['employee_rate' => 0.01, 'employer_rate' => 0.01, 'effective_to' => '2026-01-10']);

        app(PayrollComputationService::class)->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);

        $this->assertDatabaseCount('payroll_deductions', 0);
        $this->assertDatabaseCount('payroll_employer_contributions', 0);
    }

    public function test_future_tax_and_contribution_versions_do_not_rewrite_an_earlier_period(): void
    {
        $detail = $this->detail(20000);
        ContributionRate::factory()->create(['effective_from' => '2026-02-01']);
        TaxBracket::factory()->create(['effective_from' => '2026-02-01', 'marginal_tax_rate' => 0.9]);

        app(PayrollComputationService::class)->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);

        $this->assertSame('20000.00', $detail->fresh()->net_pay);
        $this->assertDatabaseCount('payroll_deductions', 0);
    }

    public function test_rates_expiring_on_the_period_end_are_still_effective(): void
    {
        $detail = $this->detail(20000);
        ContributionRate::factory()->create(['effective_to' => '2026-01-31']);
        TaxBracket::factory()->create(['effective_to' => '2026-01-31', 'marginal_tax_rate' => 0.1]);

        app(PayrollComputationService::class)->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);

        $this->assertSame('500.00', $detail->deductions()->where('deduction_name_snapshot', 'PhilHealth Contribution')->firstOrFail()->amount);
        $this->assertSame('1950.00', $detail->deductions()->where('deduction_name_snapshot', 'Withholding Tax')->firstOrFail()->amount);
    }

    public function test_expired_and_monthly_tax_tables_are_not_used_for_annual_projection(): void
    {
        $detail = $this->detail(20000);
        TaxBracket::factory()->create(['effective_to' => '2026-01-10', 'marginal_tax_rate' => 0.1]);
        TaxBracket::factory()->create(['effective_from' => '2026-01-01', 'pay_frequency' => 'MONTHLY', 'marginal_tax_rate' => 0.2]);

        app(PayrollComputationService::class)->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);

        $this->assertDatabaseCount('payroll_deductions', 0);
        $this->assertSame('20000.00', $detail->fresh()->net_pay);
    }

    public function test_lbp_is_separate_from_total_deductions_and_recomputation_does_not_duplicate_rows(): void
    {
        $detail = $this->detail(20000);
        $detail->update(['lbp_loan_deduction' => 500]);
        ContributionRate::factory()->create();
        $service = app(PayrollComputationService::class);

        $service->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);
        $service->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);

        $this->assertSame('500.00', $detail->fresh()->total_deductions);
        $this->assertSame('19500.00', $detail->fresh()->nthp_before_lbp);
        $this->assertSame('19000.00', $detail->fresh()->net_pay);
        $this->assertDatabaseCount('payroll_deductions', 1);
        $this->assertDatabaseCount('payroll_employer_contributions', 1);
    }

    public function test_a_daily_employee_with_only_absences_is_not_given_the_no_attendance_fallback(): void
    {
        $this->seed(DeductionTypeSeeder::class);
        $record = EmploymentRecord::factory()->create();
        CompensationProfile::factory()->create(['employment_id' => $record->getKey(), 'pay_basis' => 'DAILY', 'basic_rate' => 800, 'effective_from' => '2024-01-01']);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'pay_date' => '2026-01-31']);
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey()]);
        AttendanceRecord::factory()->create(['employee_id' => $record->employee_id, 'attendance_date' => '2026-01-05', 'attendance_status' => 'ABSENT', 'credited_days' => 0]);

        app(PayrollComputationService::class)->computeRun($run);

        $this->assertSame('0.00', $run->details()->firstOrFail()->basic_pay);
    }
}
