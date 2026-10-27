<?php

namespace App\Services;

use App\Enums\PersonnelClassification;
use App\Models\PayrollBatch;
use App\Models\PayrollFinalization;
use App\Rules\Utf8Text;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PayrollDocumentService
{
    public const COLUMNS = [
        'basic_pay' => 'Basic pay', 'other_earnings' => 'Other earnings', 'total_earnings' => 'Total earnings',
        'manual_deductions' => 'Loans and adjustments', 'gsis' => 'GSIS', 'pagibig' => 'Pag-IBIG',
        'philhealth' => 'PhilHealth', 'withholding' => 'Withholding tax',
        'total_deductions' => 'Total deductions', 'net_pay' => 'Net pay',
    ];

    private const INVALID = 'The preserved payroll snapshot is incomplete or inconsistent. Documents cannot be issued.';

    private function same(string $actual, string $expected): void
    {
        abort_unless(bccomp($actual, $expected, 2) === 0, 409, self::INVALID);
    }

    /** Project preserved values only. No live employee, input, rule or calculation lookup. */
    public function prepare(PayrollBatch $batch, PayrollFinalization $finalization): array
    {
        abort_unless($finalization->payroll_batch_id === $batch->id && $batch->status === 'finalized', 404);
        $snapshot = $finalization->snapshot;
        $payroll = is_array($snapshot) ? ($snapshot['payroll'] ?? null) : null;
        abort_unless(is_array($payroll) && ($snapshot['schema'] ?? null) === 'finalized-payroll-v1'
            && ($snapshot['version'] ?? null) === $finalization->version
            && ($payroll['schema'] ?? null) === 'payroll-review-v1'
            && ($payroll['payroll_batch_id'] ?? null) === $batch->id
            && ($payroll['period'] ?? null) === $batch->period_start->format('Y-m'), 409, self::INVALID);
        abort_unless($batch->is_demonstration && ($payroll['is_demonstration'] ?? null) === true
            && ($payroll['production_ready'] ?? null) === false, 409, 'Only preserved demonstration payroll documents are available pending client validation.');

        $money = ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,19})\.[0-9]{2}\z/'];
        $text = ['required', 'string', new Utf8Text];
        $rules = [
            'employees' => ['required', 'array', 'list', 'min:1'],
            'employees.*.employee.employee_id' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'employees.*.employee.employee_number' => [...$text, 'max:50'],
            'employees.*.employee.employee_name' => [...$text, 'max:350'],
            'employees.*.employee.office' => [...$text, 'max:150'],
            'employees.*.employee.position' => [...$text, 'max:150'],
            'employees.*.employee.personnel_classification' => ['required', Rule::enum(PersonnelClassification::class)],
            'employees.*.employee.grade' => ['required', 'integer', 'min:1'],
            'employees.*.employee.step' => ['required', 'integer', 'min:1'],
            'employees.*.core_breakdown.lines' => ['present', 'array', 'list', 'max:120'],
            'employees.*.core_breakdown.lines.*.label' => [...$text, 'max:100'],
            'employees.*.core_breakdown.lines.*.bucket' => ['required', Rule::in(['allowances', 'bonuses', 'earning_adjustments', 'loans', 'deduction_adjustments'])],
            'employees.*.core_breakdown.lines.*.amount' => $money,
            'employees.*.core_breakdown.lines.*.included' => ['required', 'boolean'],
            'employees.*.statutory_result.status' => ['required', Rule::in(['demonstration'])],
            'employees.*.statutory_result.total_deductions' => $money,
            'employees.*.statutory_result.net_before_tax_refund' => $money,
            'employees.*.statutory_result.tax.withholding' => $money,
        ];
        foreach (['total_earnings', 'total_deductions', 'net_pay'] as $field) {
            $rules['totals.'.$field] = $money;
        }
        foreach (PayrollCalculator::TOTAL_FIELDS as $field) {
            $rules['employees.*.core_breakdown.totals.'.$field] = $money;
        }
        foreach (['gsis', 'pagibig', 'philhealth'] as $component) {
            $rules['employees.*.statutory_result.contributions.'.$component.'.amount'] = $money;
        }
        abort_if(Validator::make($payroll, $rules)->fails(), 409, self::INVALID);

        $rows = [];
        $totals = array_fill_keys(array_keys(self::COLUMNS), '0.00');
        foreach ($payroll['employees'] as $saved) {
            abort_unless(is_int($saved['employee']['employee_id']), 409, self::INVALID);
            $core = $saved['core_breakdown']['totals'];
            $statutory = $saved['statutory_result'];
            $earnings = [['label' => 'Basic pay', 'amount' => $core['basic_pay']]];
            $deductions = [];
            $lineSums = array_fill_keys(['allowances', 'bonuses', 'earning_adjustments', 'loans', 'deduction_adjustments'], '0.00');
            foreach ($saved['core_breakdown']['lines'] as $line) {
                abort_unless(is_bool($line['included']), 409, self::INVALID);
                if (! $line['included']) {
                    continue;
                }
                $lineSums[$line['bucket']] = bcadd($lineSums[$line['bucket']], $line['amount'], 2);
                $item = ['label' => $line['label'], 'amount' => $line['amount']];
                if (in_array($line['bucket'], ['loans', 'deduction_adjustments'], true)) {
                    $deductions[] = $item;
                } else {
                    $earnings[] = $item;
                }
            }
            foreach ($lineSums as $field => $sum) {
                $this->same($core[$field], $sum);
            }
            $extra = bcadd(bcadd($core['allowances'], $core['bonuses'], 2), $core['earning_adjustments'], 2);
            $manual = bcadd($core['loans'], $core['deduction_adjustments'], 2);
            $this->same($core['total_earnings'], bcadd($core['basic_pay'], $extra, 2));
            $this->same($core['total_deductions'], $manual);
            $this->same($core['net_pay'], bcsub($core['total_earnings'], $manual, 2));
            $amounts = ['basic_pay' => $core['basic_pay'], 'other_earnings' => $extra,
                'total_earnings' => $core['total_earnings'], 'manual_deductions' => $manual];
            $deductionSum = $manual;
            foreach (['gsis' => 'GSIS', 'pagibig' => 'Pag-IBIG', 'philhealth' => 'PhilHealth'] as $component => $label) {
                $amounts[$component] = $statutory['contributions'][$component]['amount'];
                $deductions[] = ['label' => $label, 'amount' => $amounts[$component]];
                $deductionSum = bcadd($deductionSum, $amounts[$component], 2);
            }
            $amounts['withholding'] = $statutory['tax']['withholding'];
            $deductions[] = ['label' => 'Withholding tax', 'amount' => $amounts['withholding']];
            $this->same($statutory['total_deductions'], bcadd($deductionSum, $amounts['withholding'], 2));
            $this->same($statutory['net_before_tax_refund'], bcsub($core['total_earnings'], $statutory['total_deductions'], 2));
            $amounts['total_deductions'] = $statutory['total_deductions'];
            $amounts['net_pay'] = $statutory['net_before_tax_refund'];
            foreach ($amounts as $field => $amount) {
                $totals[$field] = bcadd($totals[$field], $amount, 2);
            }
            $rows[] = ['employee' => $saved['employee'], 'amounts' => $amounts, 'earnings' => $earnings, 'deductions' => $deductions];
        }
        foreach (['total_earnings', 'total_deductions', 'net_pay'] as $field) {
            $this->same($totals[$field], $payroll['totals'][$field]);
        }

        return ['period' => $payroll['period'], 'rows' => $rows, 'totals' => $totals, 'columns' => self::COLUMNS];
    }

    public function employee(array $document, string $id): array
    {
        foreach ($document['rows'] as $row) {
            if ((string) $row['employee']['employee_id'] === $id) {
                return $row;
            }
        }
        abort(404);
    }

    public function csv(array $document, PayrollFinalization $finalization): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        $safe = fn (string $value) => preg_match('/\A[\x00-\x20]*[=+\-@]|\A[\t\r\n]/u', $value) ? "'".$value : $value;
        fputcsv($stream, ['Record type', 'Period', 'Batch ID', 'Finalization ID', 'Version', 'Data use',
            'Employee number', 'Employee name', 'Position', 'Office', 'Classification', 'Grade', 'Step', ...array_values(self::COLUMNS)], ',', '"', '');
        $scope = [$document['period'], (string) $finalization->payroll_batch_id, (string) $finalization->id,
            (string) $finalization->version, 'DEMONSTRATION ONLY - NOT FOR REAL PAYROLL'];
        foreach ($document['rows'] as $row) {
            $employee = $row['employee'];
            fputcsv($stream, ['EMPLOYEE', ...$scope, ...array_map($safe, [
                $employee['employee_number'], $employee['employee_name'], $employee['position'], $employee['office'],
                $employee['personnel_classification'], (string) $employee['grade'], (string) $employee['step'],
            ]), ...array_values($row['amounts'])], ',', '"', '');
        }
        fputcsv($stream, ['TOTAL', ...$scope, ...array_fill(0, 7, ''), ...array_values($document['totals'])], ',', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    public function pdf(string $view, array $data, bool $landscape): string
    {
        $cache = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cache);
        $options = new Options([
            'defaultFont' => 'DejaVu Sans', 'tempDir' => $cache, 'fontCache' => $cache,
            'chroot' => [base_path('vendor/dompdf/dompdf/lib/fonts')],
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true, 'allowedProtocols' => ['file://'],
        ]);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view, [...$data, 'pdf' => true])->render(), 'UTF-8');
        $pdf->setPaper('A4', $landscape ? 'landscape' : 'portrait');
        $pdf->render();
        $pdf->getCanvas()->page_text(36, $landscape ? 573 : 817, 'Page {PAGE_NUM} of {PAGE_COUNT}', $pdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.3, 0.3, 0.3]);

        return $pdf->output();
    }
}
