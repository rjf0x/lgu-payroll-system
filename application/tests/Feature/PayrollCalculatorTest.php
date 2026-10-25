<?php

namespace Tests\Feature;

use App\Services\PayrollCalculator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollCalculatorTest extends TestCase
{
    private static function emptyInputs(): array
    {
        return ['allowances' => [], 'bonuses' => [], 'loans' => [], 'adjustments' => [], 'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0]];
    }

    public static function preparedCases(): array
    {
        $full = self::emptyInputs();
        $full['allowances'] = [['label' => 'A', 'amount' => '100.07', 'is_applicable' => true], ['label' => 'B', 'amount' => '200.08', 'is_applicable' => true], ['label' => 'Excluded', 'amount' => '999.99', 'is_applicable' => false]];
        $full['bonuses'] = [['label' => 'Bonus A', 'amount' => '500.01'], ['label' => 'Bonus B', 'amount' => '0.02']];
        $full['loans'] = [['label' => 'Loan A', 'amount' => '200.02'], ['label' => 'Loan B', 'amount' => '30.03']];
        $full['adjustments'] = [
            ['label' => 'Earning', 'direction' => 'earning', 'original_amount' => '999.99', 'adjusted_amount' => '50.09', 'reason' => 'Fictional earning replacement'],
            ['label' => 'Deduction', 'direction' => 'deduction', 'original_amount' => '300.03', 'adjusted_amount' => '111.11', 'reason' => 'Fictional deduction replacement'],
        ];
        $negative = self::emptyInputs();
        $negative['loans'] = [['label' => 'Loan', 'amount' => '160.00']];
        $large = self::emptyInputs();
        foreach (range(1, 30) as $index) {
            $large['allowances'][] = ['label' => 'Allowance '.$index, 'amount' => '9999999999.99', 'is_applicable' => true];
            $large['bonuses'][] = ['label' => 'Bonus '.$index, 'amount' => '9999999999.99'];
            $large['loans'][] = ['label' => 'Loan '.$index, 'amount' => '9999999999.99'];
            $large['adjustments'][] = ['label' => 'Adjustment '.$index, 'direction' => $index <= 15 ? 'earning' : 'deduction', 'original_amount' => '0.00', 'adjusted_amount' => '9999999999.99', 'reason' => 'Fictional boundary case'];
        }

        // Literal expected results were prepared separately from the calculator.
        return [
            'full month with excluded line and replacements' => ['12345.67', $full, ['12345.67', '300.15', '500.03', '50.09', '13195.94', '230.05', '111.11', '341.16', '12854.78'], false],
            'basic salary only' => ['1000.00', self::emptyInputs(), ['1000.00', '0.00', '0.00', '0.00', '1000.00', '0.00', '0.00', '0.00', '1000.00'], false],
            'negative retained without flooring' => ['100.00', $negative, ['100.00', '0.00', '0.00', '0.00', '100.00', '160.00', '0.00', '160.00', '-60.00'], true],
            'maximum line values and aggregate width' => ['9999999999.99', $large, ['9999999999.99', '299999999999.70', '299999999999.70', '149999999999.85', '759999999999.24', '299999999999.70', '149999999999.85', '449999999999.55', '309999999999.69'], false],
        ];
    }

    #[DataProvider('preparedCases')]
    public function test_totals_match_independently_prepared_cases(string $salary, array $payload, array $expected, bool $negative): void
    {
        $original = $payload;
        $calculator = app(PayrollCalculator::class);
        $result = $calculator->calculate($salary, $payload);
        $this->assertSame(array_combine(PayrollCalculator::TOTAL_FIELDS, $expected), $result['totals']);
        $this->assertSame($negative, $result['negative_net']);
        $this->assertSame($original, $payload);
        $this->assertNull($result['statutory_deductions']);
        $this->assertNull($result['attendance_deduction']);
        $this->assertSame($result, $calculator->calculate($salary, $payload));
        if ($salary === '12345.67') {
            $excluded = array_values(array_filter($result['lines'], fn ($line) => ! $line['included']));
            $this->assertSame('999.99', $excluded[0]['amount']);
            $this->assertCount(1, $excluded);
        }
    }

    public static function invalidAmounts(): array
    {
        return [['1.001'], ['1e3'], ['-1.00'], ['01.00'], [1.25], [['bad']], ['99999999999.99']];
    }

    #[DataProvider('invalidAmounts')]
    public function test_unrounded_or_invalid_saved_amounts_are_not_silently_changed(mixed $amount): void
    {
        $payload = self::emptyInputs();
        $payload['loans'] = [['label' => 'Fictional invalid loan', 'amount' => $amount]];
        $this->expectException(ValidationException::class);
        app(PayrollCalculator::class)->calculate('1000.00', $payload);
    }

    public function test_unknown_applicability_and_nonzero_attendance_are_not_guessed(): void
    {
        foreach (['absence', 'undertime', 'applicability'] as $case) {
            $payload = self::emptyInputs();
            if ($case === 'absence') {
                $payload['attendance']['absence_days'] = '1.00';
            } elseif ($case === 'undertime') {
                $payload['attendance']['undertime_minutes'] = 1;
            } else {
                $payload['allowances'] = [['label' => 'Fictional unknown', 'amount' => '1.00', 'is_applicable' => '1']];
            }
            try {
                app(PayrollCalculator::class)->calculate('1000.00', $payload);
                $this->fail('An unknown rule/input was silently applied.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }
}
