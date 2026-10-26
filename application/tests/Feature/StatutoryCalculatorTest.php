<?php

namespace Tests\Feature;

use App\Services\StatutoryCalculator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StatutoryCalculatorTest extends TestCase
{
    public static function data(array $overrides = []): array
    {
        return array_replace([
            'record_version' => 1, 'base_revision' => 0,
            'gsis_coverage' => 'applicable', 'gsis_basis' => '30000.00',
            'pagibig_coverage' => 'applicable', 'pagibig_basis' => '30000.00',
            'philhealth_coverage' => 'applicable', 'philhealth_basis' => '30000.00',
            'tax_method' => 'monthly_regular', 'taxable_regular' => '26350.00',
            'taxable_other' => '0.00', 'qualifying_bonuses' => '0.00', 'prior_withheld' => '0.00',
            'elapsed_periods' => null, 'reference' => 'FICTIONAL TEST AUTHORITY',
            'reason' => 'Reviewed fictional bases, classification and year-to-date figures', 'demonstration_confirmed' => 1,
        ], $overrides);
    }

    public static function core(): array
    {
        return ['totals' => ['total_earnings' => '30000.00', 'total_deductions' => '0.00', 'bonuses' => '0.00', 'earning_adjustments' => '0.00']];
    }

    private function calculate(array $overrides = [], ?array $profile = null, string $period = '2026-10'): array
    {
        $calculator = app(StatutoryCalculator::class);

        return $calculator->calculate(self::core(), $calculator->normalize(self::data($overrides)), $profile ?? config('payroll_statutory'), 'permanent', $period);
    }

    public function test_literal_monthly_reference_case_and_no_input_mutation(): void
    {
        $input = self::data();
        $before = $input;
        $result = $this->calculate();
        $this->assertSame(['2700.00', '200.00', '750.00'], array_column($result['contributions'], 'amount'));
        $this->assertSame('827.55', $result['tax']['withholding']);
        $this->assertSame('4477.55', $result['total_deductions']);
        $this->assertSame('25522.45', $result['net_before_tax_refund']);
        $this->assertFalse($result['production_ready']);
        $this->assertSame($before, $input);
        $this->assertSame($result, $this->calculate());
    }

    public static function contributions(): array
    {
        return [
            ['1.00', '0.09', '0.01', '250.00'],
            ['1500.00', '135.00', '15.00', '250.00'],
            ['1500.01', '135.00', '30.00', '250.00'],
            ['10000.00', '900.00', '200.00', '250.00'],
            ['10000.20', '900.02', '200.00', '250.01'],
            ['12345.67', '1111.11', '200.00', '308.64'],
            ['100000.00', '9000.00', '200.00', '2500.00'],
            ['100000.01', '9000.00', '200.00', '2500.00'],
        ];
    }

    #[DataProvider('contributions')]
    public function test_contribution_thresholds_floors_caps_and_half_cent_rounding(string $basis, string $gsis, string $pagibig, string $philhealth): void
    {
        $result = $this->calculate(['gsis_basis' => $basis, 'pagibig_basis' => $basis, 'philhealth_basis' => $basis]);
        $this->assertSame([$gsis, $pagibig, $philhealth], array_column($result['contributions'], 'amount'));
    }

    public static function taxBrackets(): array
    {
        return [
            ['monthly_regular', '0.00', '0.00'], ['monthly_regular', '20833.00', '0.00'],
            ['monthly_regular', '20833.01', '0.00'], ['monthly_regular', '33332.99', '1875.00'],
            ['monthly_regular', '33333.00', '1875.00'], ['monthly_regular', '66667.00', '8541.80'],
            ['monthly_regular', '166667.00', '33541.80'], ['monthly_regular', '666667.00', '183541.80'],
            ['monthly_regular', '700000.00', '195208.35'],
            ['annual', '250000.00', '0.00'], ['annual', '400000.00', '22500.00'],
            ['annual', '800000.00', '102500.00'], ['annual', '2000000.00', '402500.00'],
            ['annual', '8000000.00', '2202500.00'], ['annual', '9000000.00', '2552500.00'],
        ];
    }

    #[DataProvider('taxBrackets')]
    public function test_literal_tax_table_results(string $method, string $basis, string $expected): void
    {
        $result = $this->calculate(['tax_method' => $method, 'taxable_regular' => $basis], period: '2026-12');
        $this->assertSame($expected, $result['tax']['withholding']);
    }

    public function test_taxable_bonus_excess_is_included_once_in_annual_compensation(): void
    {
        $result = $this->calculate(['tax_method' => 'annual', 'taxable_regular' => '500000.00', 'taxable_other' => '10000.00', 'qualifying_bonuses' => '135000.00', 'prior_withheld' => '50000.00'], period: '2026-12');
        $this->assertSame('45000.00', $result['tax']['taxable_bonus_excess']);
        $this->assertSame('555000.00', $result['tax']['taxable_compensation']);
        $this->assertSame('53500.00', $result['tax']['liability']);
        $this->assertSame('3500.00', $result['tax']['withholding']);
        $changed = $this->calculate(['tax_method' => 'annual', 'taxable_regular' => '500000.00', 'qualifying_bonuses' => '91000.00'], period: '2026-12');
        $this->assertSame('1000.00', $changed['tax']['taxable_bonus_excess']);
        $this->assertSame('501000.00', $changed['tax']['taxable_compensation']);
        $zero = $this->calculate(['tax_method' => 'annual', 'qualifying_bonuses' => '89999.99'], period: '2026-12');
        $this->assertSame('0.00', $zero['tax']['taxable_bonus_excess']);
    }

    public function test_cumulative_average_counts_bonus_excess_and_previous_withholding(): void
    {
        $result = $this->calculate(['tax_method' => 'cumulative', 'taxable_regular' => '300000.00', 'taxable_other' => '5000.00', 'qualifying_bonuses' => '100000.00', 'prior_withheld' => '15000.00', 'elapsed_periods' => 10]);
        $this->assertSame('315000.00', $result['tax']['taxable_compensation']);
        $this->assertSame('31500.00000000', $result['tax']['table_basis']);
        $this->assertSame('16000.50', $result['tax']['liability']);
        $this->assertSame('1000.50', $result['tax']['withholding']);
        $this->assertSame('25349.50', $result['net_before_tax_refund']);
    }

    public function test_overwithholding_is_a_separate_credit_and_does_not_silently_increase_net(): void
    {
        $result = $this->calculate(['tax_method' => 'annual', 'taxable_regular' => '400000.00', 'prior_withheld' => '25000.00'], period: '2026-12');
        $this->assertSame('2500.00', $result['tax']['credit_or_refund_due']);
        $this->assertSame('0.00', $result['tax']['withholding']);
        $this->assertSame('26350.00', $result['net_before_tax_refund']);
    }

    public function test_cumulative_rounding_occurs_after_multiplying_by_elapsed_periods(): void
    {
        $result = $this->calculate(['tax_method' => 'cumulative', 'taxable_regular' => '100000.00', 'elapsed_periods' => 3]);
        $this->assertSame('33333.33333333', $result['tax']['table_basis']);
        $this->assertSame('5625.20', $result['tax']['liability']);
    }

    public static function cumulativeCentBoundaries(): array
    {
        return [
            ['75000.10', 3, '1875.17'], ['150000.10', 6, '3750.32'],
            ['300000.01', 3, '50625.15'], ['300000.02', 3, '50625.16'],
            ['300000.03', 3, '50625.16'], ['600000.02', 6, '101250.31'],
            ['700000.02', 7, '118125.36'], ['1100000.02', 11, '185625.56'],
            ['900000.05', 3, '220625.12'], ['3000000.10', 3, '900625.09'],
        ];
    }

    #[DataProvider('cumulativeCentBoundaries')]
    public function test_cumulative_liability_rounds_exact_cent_boundaries(string $basis, int $periods, string $expected): void
    {
        $result = $this->calculate(['tax_method' => 'cumulative', 'taxable_regular' => $basis, 'elapsed_periods' => $periods], period: '2026-12');
        $this->assertSame($expected, $result['tax']['liability']);
        $this->assertSame($expected, $result['tax']['withholding']);
        $this->assertFalse($result['production_ready']);
    }

    public static function malformedTables(): array
    {
        return [['associative-row'], ['sparse-row'], ['associative-table'], ['sparse-table'], ['extra-row-value'], ['scalar-row']];
    }

    #[DataProvider('malformedTables')]
    public function test_malformed_bracket_shapes_leave_totals_unavailable(string $shape): void
    {
        $profile = config('payroll_statutory');
        $table = $profile['tax']['monthly_brackets'];
        switch ($shape) {
            case 'associative-row':
                $table[1] = ['lower' => '20833.00', 'fixed' => '0.00', 'rate' => '0.15'];
                break;
            case 'sparse-row':
                $table[1] = [0 => '20833.00', 2 => '0.00', 3 => '0.15'];
                break;
            case 'associative-table':
                $table = ['first' => $table[0], 'second' => $table[1]];
                break;
            case 'sparse-table':
                unset($table[0]);
                break;
            case 'extra-row-value':
                $table[1][] = '0.00';
                break;
            case 'scalar-row':
                $table[1] = 'invalid';
                break;
        }
        $profile['tax']['monthly_brackets'] = $table;
        $result = $this->calculate(profile: $profile);
        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['tax']['withholding']);
        $this->assertNull($result['net_before_tax_refund']);
        $this->assertNotEmpty($result['issues']);
    }

    public static function invalidContributionParameters(): array
    {
        return [
            ['pagibig', 'ceiling', '0.00'], ['pagibig', 'threshold', '0.00'],
            ['pagibig', 'threshold', '10000.00'], ['pagibig', 'threshold', '10000.01'],
            ['pagibig', 'low_rate', '0.03'], ['pagibig', 'rate', '0.00'],
            ['philhealth', 'floor', '0.00'], ['philhealth', 'floor', '100000.01'],
            ['philhealth', 'employee_share', '0.00'], ['philhealth', 'employee_share', '1.01'],
            ['gsis', 'rate', '0.00'], ['gsis', 'rate', '1.01'],
        ];
    }

    #[DataProvider('invalidContributionParameters')]
    public function test_invalid_contribution_ranges_leave_totals_unavailable(string $component, string $field, string $value): void
    {
        $profile = config('payroll_statutory');
        $profile['contributions'][$component][$field] = $value;
        $result = $this->calculate(profile: $profile);
        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['contributions'][$component]['amount']);
        $this->assertNull($result['net_before_tax_refund']);
        $this->assertNotEmpty($result['issues']);
    }

    public function test_saved_supplementary_earnings_cannot_be_ignored_by_regular_only_method(): void
    {
        $calculator = app(StatutoryCalculator::class);
        $core = self::core();
        $core['totals']['bonuses'] = '500.00';
        $result = $calculator->calculate($core, $calculator->normalize(self::data()), config('payroll_statutory'), 'permanent', '2026-10');
        $this->assertNull($result['tax']['withholding']);
        $this->assertNull($result['net_before_tax_refund']);
        $this->assertStringContainsString('supplementary', implode(' ', $result['issues']));
    }

    public static function incompleteInputs(): array
    {
        return [
            [['gsis_coverage' => 'unknown']], [['pagibig_basis' => null]], [['philhealth_basis' => '0.00']],
            [['tax_method' => 'unknown']], [['taxable_regular' => null]], [['qualifying_bonuses' => null]],
            [['tax_method' => 'monthly_regular', 'qualifying_bonuses' => '1.00']],
            [['tax_method' => 'cumulative', 'elapsed_periods' => null]],
            [['tax_method' => 'cumulative', 'elapsed_periods' => 11]], [['tax_method' => 'annual']],
        ];
    }

    #[DataProvider('incompleteInputs')]
    public function test_incomplete_applicability_or_procedure_never_becomes_a_zero_net(array $overrides): void
    {
        $result = $this->calculate($overrides);
        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['total_deductions']);
        $this->assertNull($result['net_before_tax_refund']);
        $this->assertNotEmpty($result['issues']);
        $this->assertFalse($result['production_ready']);
    }

    public function test_explicit_nonapplicability_is_different_from_unknown(): void
    {
        $result = $this->calculate(['gsis_coverage' => 'not_applicable', 'gsis_basis' => null]);
        $this->assertSame('0.00', $result['contributions']['gsis']['amount']);
        $this->assertSame('demonstration', $result['status']);
    }

    public function test_missing_bad_rules_unknown_categories_and_other_years_are_blocked(): void
    {
        $profile = config('payroll_statutory');
        unset($profile['contributions']['gsis']);
        $this->assertNull($this->calculate(profile: $profile)['net_before_tax_refund']);
        $profile = config('payroll_statutory');
        $profile['tax']['monthly_brackets'][1][2] = 'not-a-rate';
        $this->assertNull($this->calculate(profile: $profile)['tax']['withholding']);
        $profile = config('payroll_statutory');
        unset($profile['category_validation']['permanent']);
        $this->assertNull($this->calculate(profile: $profile)['net_before_tax_refund']);
        $this->assertNull($this->calculate(period: '2027-10')['net_before_tax_refund']);
    }

    public function test_rate_parameters_are_configurable_without_changing_preserved_input(): void
    {
        $profile = config('payroll_statutory');
        $profile['contributions']['gsis']['rate'] = '0.10';
        $profile['tax']['bonus_exemption'] = '80000.00';
        $result = $this->calculate(['tax_method' => 'annual', 'qualifying_bonuses' => '100000.00'], $profile, '2026-12');
        $this->assertSame('3000.00', $result['contributions']['gsis']['amount']);
        $this->assertSame('20000.00', $result['tax']['taxable_bonus_excess']);
    }

    public static function invalidMoney(): array
    {
        return [['1.001'], ['1e3'], ['-1.00'], [1.25], [['bad']], ['99999999999.99']];
    }

    #[DataProvider('invalidMoney')]
    public function test_invalid_money_is_rejected_instead_of_rounded(mixed $amount): void
    {
        $this->expectException(ValidationException::class);
        $this->calculate(['gsis_basis' => $amount]);
    }
}
