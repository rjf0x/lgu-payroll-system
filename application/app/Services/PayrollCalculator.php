<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class PayrollCalculator
{
    public const VERSION = 'monthly-demo-v1';

    public const TOTAL_FIELDS = ['basic_pay', 'allowances', 'bonuses', 'earning_adjustments', 'total_earnings', 'loans', 'deduction_adjustments', 'total_deductions', 'net_pay'];

    private function money(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/\A(?:0|[1-9][0-9]{0,9})\.[0-9]{2}\z/', $value)) {
            throw ValidationException::withMessages(['inputs' => 'Calculation requires normalized, nonnegative two-decimal source amounts. Review and save the inputs again.']);
        }

        return $value;
    }

    public function calculate(string $monthlySalary, array $payload): array
    {
        $basic = $this->money($monthlySalary);
        $attendance = $payload['attendance'] ?? [];
        if (($attendance['absence_days'] ?? null) !== '0.00' || ($attendance['undertime_minutes'] ?? null) !== 0) {
            throw ValidationException::withMessages(['attendance' => 'Approved attendance deduction rules are unavailable. This demonstration engine supports only zero absence days and zero undertime; it does not infer manual attendance treatment.']);
        }
        $totals = array_fill_keys(self::TOTAL_FIELDS, '0.00');
        $totals['basic_pay'] = $basic;
        $lines = [];
        foreach (['allowances', 'bonuses', 'loans', 'adjustments'] as $group) {
            if (! isset($payload[$group]) || ! is_array($payload[$group]) || count($payload[$group]) > 30) {
                throw ValidationException::withMessages(['inputs' => 'The saved input groups are incomplete or invalid.']);
            }
            foreach ($payload[$group] as $row) {
                if (! is_array($row) || ! is_string($row['label'] ?? null) || trim($row['label']) === '') {
                    throw ValidationException::withMessages(['inputs' => 'A saved input description is invalid.']);
                }
                if ($group === 'adjustments') {
                    $this->money($row['original_amount'] ?? null);
                    $amount = $this->money($row['adjusted_amount'] ?? null);
                    if (! in_array($row['direction'] ?? null, ['earning', 'deduction'], true) || ! is_string($row['reason'] ?? null) || trim($row['reason']) === '') {
                        throw ValidationException::withMessages(['inputs' => 'A saved adjustment needs a direction and authorization reason.']);
                    }
                    $bucket = $row['direction'] === 'earning' ? 'earning_adjustments' : 'deduction_adjustments';
                    $included = true;
                } else {
                    $amount = $this->money($row['amount'] ?? null);
                    $bucket = $group;
                    if ($group === 'allowances' && ! is_bool($row['is_applicable'] ?? null)) {
                        throw ValidationException::withMessages(['inputs' => 'Saved allowance applicability must be explicit.']);
                    }
                    $included = $group !== 'allowances' || $row['is_applicable'];
                }
                if ($included) {
                    $totals[$bucket] = bcadd($totals[$bucket], $amount, 2);
                }
                $lines[] = [
                    'group' => $group, 'label' => $row['label'], 'bucket' => $bucket,
                    'amount' => $amount, 'included' => $included,
                    'original_amount' => $row['original_amount'] ?? null, 'reason' => $row['reason'] ?? null,
                ];
            }
        }
        foreach (['basic_pay', 'allowances', 'bonuses', 'earning_adjustments'] as $field) {
            $totals['total_earnings'] = bcadd($totals['total_earnings'], $totals[$field], 2);
        }
        $totals['total_deductions'] = bcadd($totals['loans'], $totals['deduction_adjustments'], 2);
        $totals['net_pay'] = bcsub($totals['total_earnings'], $totals['total_deductions'], 2);

        return [
            'totals' => $totals, 'lines' => $lines, 'negative_net' => bccomp($totals['net_pay'], '0.00', 2) < 0,
            'basis' => 'Full-month authorized salary reference; no proration. Adjustment replacement amounts are counted once; original amounts are audit references.',
            'attendance_deduction' => null, 'statutory_deductions' => null,
            'excluded_components' => ['GSIS', 'Pag-IBIG', 'PhilHealth', 'Withholding tax', 'Attendance deduction formulas'],
        ];
    }
}
