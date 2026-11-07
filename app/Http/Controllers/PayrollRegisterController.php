<?php

namespace App\Http\Controllers;

use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\GeneratedReport;
use App\Models\PayrollRun;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRegisterController extends Controller
{
    /** Number of leading text columns before the monetary columns. */
    private const TEXT_COLUMNS = 4;

    public function show(PayrollRun $payrollRun): View
    {
        return view('payroll-runs.register', $this->registerData($payrollRun) + ['run' => $payrollRun]);
    }

    public function export(PayrollRun $payrollRun): StreamedResponse
    {
        $data = $this->registerData($payrollRun);

        GeneratedReport::create([
            'report_type' => 'PAYROLL_REGISTER',
            'generated_by' => auth()->id(),
            'filters' => ['payroll_run_id' => $payrollRun->payroll_run_id],
            'generated_at' => now(),
        ]);

        $filename = 'payroll-register-'.$payrollRun->payroll_run_id.'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map($this->safeCsvCell(...), $data['headers']), escape: '');

            foreach ([...$data['rows'], $data['totals']] as $row) {
                fputcsv($out, array_map(
                    fn ($cell) => is_float($cell) ? number_format($cell, 2, '.', '') : $this->safeCsvCell((string) $cell),
                    $row,
                ), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function safeCsvCell(string $value): string
    {
        return preg_match('/^(?:[\t\r\n]|\s*[=+@-])/u', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>, totals: array<int, mixed>}
     */
    private function registerData(PayrollRun $run): array
    {
        $run->load(['payrollPeriod', 'details.earnings', 'details.deductions']);

        $earningNames = EarningType::where('status', 'ACTIVE')->orderBy('earning_name')->pluck('earning_name')->all();

        $deductionTypes = DeductionType::where('is_statutory', false)
            ->where('deduction_category', '!=', 'LBP')
            ->where('status', 'ACTIVE')
            ->orderBy('deduction_category')
            ->orderBy('deduction_name')
            ->get();

        $nameCounts = $deductionTypes->countBy('deduction_name');

        $statutoryIds = DeductionType::whereIn('deduction_name', [
            'Withholding Tax', 'GSIS Contribution', 'PhilHealth Contribution', 'Pag-IBIG Contribution',
        ])->pluck('deduction_type_id', 'deduction_name');

        $headers = array_merge(
            ['Office', 'Employee Number', 'Name', 'Position', 'Gross Salary'],
            $earningNames,
            ['Total Compensation', 'Withholding Tax', 'GSIS Contrib.', 'PhilHealth Contrib.', 'Pag ibig Contrib.'],
            $deductionTypes->map(fn (DeductionType $type) => $nameCounts[$type->deduction_name] > 1
                ? $type->deduction_name.' ('.$type->deduction_category.')'
                : $type->deduction_name)->all(),
            ['TOTAL DEDUCTIONS', 'NTHP Before LBP', 'LBP Loan Deduction', 'Net Take Home Pay'],
        );

        $rows = [];

        foreach ($run->details as $detail) {
            $earnings = $detail->earnings->groupBy('earning_name_snapshot')->map(fn ($group) => round((float) $group->sum('amount'), 2));
            $deductions = $detail->deductions->groupBy('deduction_type_id')->map(fn ($group) => round((float) $group->sum('amount'), 2));

            $row = [
                $detail->department_snapshot ?? '',
                $detail->employee_number_snapshot,
                $detail->employee_name_snapshot,
                $detail->position_snapshot ?? '',
                round((float) $detail->basic_pay, 2),
            ];

            foreach ($earningNames as $name) {
                $row[] = (float) ($earnings[$name] ?? 0);
            }

            $row[] = round((float) $detail->gross_pay, 2);
            $row[] = (float) ($deductions[$statutoryIds['Withholding Tax'] ?? 0] ?? 0);
            $row[] = (float) ($deductions[$statutoryIds['GSIS Contribution'] ?? 0] ?? 0);
            $row[] = (float) ($deductions[$statutoryIds['PhilHealth Contribution'] ?? 0] ?? 0);
            $row[] = (float) ($deductions[$statutoryIds['Pag-IBIG Contribution'] ?? 0] ?? 0);

            foreach ($deductionTypes as $type) {
                $row[] = (float) ($deductions[$type->deduction_type_id] ?? 0);
            }

            $row[] = round((float) $detail->total_deductions, 2);
            $row[] = round((float) $detail->nthp_before_lbp, 2);
            $row[] = round((float) $detail->lbp_loan_deduction, 2);
            $row[] = round((float) $detail->net_pay, 2);

            $rows[] = $row;
        }

        $totals = [];
        foreach ($headers as $index => $header) {
            $totals[] = $index < self::TEXT_COLUMNS
                ? ($index === 0 ? 'TOTAL' : '')
                : round(array_sum(array_map(fn ($row) => (float) $row[$index], $rows)), 2);
        }

        return ['headers' => $headers, 'rows' => $rows, 'totals' => $totals];
    }
}
