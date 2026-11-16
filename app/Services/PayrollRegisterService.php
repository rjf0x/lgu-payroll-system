<?php

namespace App\Services;

use App\Models\PayrollDeduction;
use App\Models\PayrollEarning;
use App\Models\PayrollEmployerContribution;
use App\Models\PayrollRun;

class PayrollRegisterService
{
    public const NOTES = 'Saved payroll labels and amounts; identifiers distinguish components. Missing historical labels use the saved identifier only. Reconciliation differences are saved totals minus their supporting amounts. Employer contributions are not deducted from pay.';

    /**
     * Never consult current configuration for a historical label or classification.
     * Blank legacy snapshots use an explicit identifier-based fallback, without backfilling
     * payroll data. The existing name snapshots already form part of the integrity payload.
     */
    public function label(PayrollEarning|PayrollDeduction|PayrollEmployerContribution $line): string
    {
        [$kind, $identifier, $snapshot] = $this->identity($line);
        if ($snapshot === null) {
            return $kind.' #'.$identifier.' (historical label unavailable)';
        }

        return $snapshot.' ['.$kind.' #'.$identifier.']';
    }

    /** Same identifiers with different saved descriptions remain separate columns. */
    public function key(PayrollEarning|PayrollDeduction|PayrollEmployerContribution $line): string
    {
        return json_encode($this->identity($line), JSON_THROW_ON_ERROR);
    }

    /**
     * Columns follow the first persisted line-item order within each component section.
     * Totals always come from saved details; discrepancies are reported, never repaired.
     *
     * @return array{headers: list<string>, column_keys: list<string>, rows: list<list<mixed>>, totals: list<mixed>, reportNotes: string}
     */
    public function build(PayrollRun $run): array
    {
        $run->load(['payrollPeriod', 'details.earnings', 'details.deductions', 'details.employerContributions']);
        $sections = ['earnings' => [], 'deductions' => [], 'employerContributions' => []];
        foreach (array_keys($sections) as $relation) {
            foreach ($run->details->flatMap(fn ($detail) => $detail->$relation)->sortBy(fn ($line) => $line->getKey()) as $line) {
                $sections[$relation][$this->key($line)] = $this->label($line);
            }
        }

        $headers = [
            'department_snapshot' => 'Office', 'employee_number_snapshot' => 'Employee Number',
            'employee_name_snapshot' => 'Name', 'position_snapshot' => 'Position', 'basic_pay' => 'Gross Salary',
            ...$sections['earnings'], 'total_earnings' => 'Total Earnings', 'gross_pay' => 'Total Compensation',
            ...$sections['deductions'], 'total_deductions' => 'TOTAL DEDUCTIONS',
            'nthp_before_lbp' => 'NTHP Before LBP', 'lbp_loan_deduction' => 'LBP Loan Deduction', 'net_pay' => 'Net Take Home Pay',
            ...$sections['employerContributions'],
        ];
        $rows = [];
        $differences = [
            'earnings_difference' => 'Earnings reconciliation difference',
            'gross_difference' => 'Gross pay reconciliation difference',
            'deductions_difference' => 'Deductions reconciliation difference',
            'nthp_difference' => 'NTHP reconciliation difference',
            'net_difference' => 'Net pay reconciliation difference',
        ];
        foreach ($run->details->sortBy('payroll_detail_id') as $detail) {
            $row = [];
            foreach (array_keys($headers) as $index => $key) {
                $row[$key] = $index < 4 ? ($detail->$key ?? '') : $this->cents($detail->getAttributes()[$key] ?? 0);
            }
            $sums = [];
            foreach ($sections as $relation => $columns) {
                $sums[$relation] = 0;
                foreach ($detail->$relation as $line) {
                    $amount = $this->cents($line->amount);
                    $row[$this->key($line)] += $amount;
                    $sums[$relation] += $amount;
                }
            }
            $row['earnings_difference'] = $row['total_earnings'] - $sums['earnings'];
            $row['gross_difference'] = $row['gross_pay'] - $row['basic_pay'] - $row['total_earnings'];
            $row['deductions_difference'] = $row['total_deductions'] - $sums['deductions'];
            $row['nthp_difference'] = $row['nthp_before_lbp'] - ($row['gross_pay'] - $row['total_deductions']);
            $row['net_difference'] = $row['net_pay'] - ($row['nthp_before_lbp'] - $row['lbp_loan_deduction']);
            $rows[] = $row;
        }
        foreach ($differences as $key => $label) {
            if (array_any($rows, fn (array $row): bool => $row[$key] !== 0)) {
                $headers[$key] = $label;
            }
        }
        $totals = [];
        foreach (array_keys($headers) as $index => $key) {
            $totals[] = $index < 4 ? ($index === 0 ? 'TOTAL' : '') : (float) (array_sum(array_column($rows, $key)) / 100);
        }
        $values = array_map(function (array $row) use ($headers): array {
            $values = [];
            foreach (array_keys($headers) as $index => $key) {
                $values[] = $index < 4 ? $row[$key] : (float) ($row[$key] / 100);
            }

            return $values;
        }, $rows);

        return ['headers' => array_values($headers), 'column_keys' => array_keys($headers), 'rows' => $values, 'totals' => $totals, 'reportNotes' => self::NOTES];
    }

    /** @return array{string, int, ?string} */
    private function identity(PayrollEarning|PayrollDeduction|PayrollEmployerContribution $line): array
    {
        $identity = match (true) {
            $line instanceof PayrollEarning => ['Earning', $line->earning_type_id, $line->earning_name_snapshot],
            $line instanceof PayrollDeduction => ['Deduction', $line->deduction_type_id, $line->deduction_name_snapshot],
            default => ['Employer rate', $line->contribution_rate_id, $line->contribution_type_snapshot],
        };
        if (trim((string) $identity[2]) === '') {
            $identity[2] = null;
        }

        return $identity;
    }

    private function cents(string|int|float|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
