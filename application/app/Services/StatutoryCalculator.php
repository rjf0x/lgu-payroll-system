<?php

namespace App\Services;

use App\Http\Requests\PreviewStatutoryRequest;
use Illuminate\Support\Facades\Validator;

class StatutoryCalculator
{
    public const VERSION = 'statutory-reference-v2';

    public function normalize(array $data): array
    {
        // The service also validates callers which do not use an HTTP FormRequest.
        $request = new PreviewStatutoryRequest;
        $data = Validator::make($data, $request->rules())->validate();
        foreach (['taxable_regular', 'taxable_other', 'qualifying_bonuses', 'prior_withheld', 'gsis_basis', 'pagibig_basis', 'philhealth_basis'] as $field) {
            $data[$field] = isset($data[$field]) ? bcadd($data[$field], '0', 2) : null;
        }
        $data['elapsed_periods'] = isset($data['elapsed_periods']) ? (int) $data['elapsed_periods'] : null;
        unset($data['record_version'], $data['base_revision'], $data['demonstration_confirmed'], $data['configuration_version_id']);

        return $data;
    }

    private function rounded(string $value): string
    {
        return bcadd($value, '0.005', 2); // All rate results are nonnegative.
    }

    private function missing(array &$issues, string $message): null
    {
        $issues[] = $message;

        return null;
    }

    private function ruleValid(mixed $rule, string $method): bool
    {
        if (! is_array($rule) || ($rule['method'] ?? null) !== $method || ! is_string($rule['source'] ?? null) || trim($rule['source']) === '') {
            return false;
        }
        $fields = match ($method) {
            'percentage' => ['rate'],
            'tiered_percentage' => ['rate', 'low_rate', 'threshold', 'ceiling'],
            'shared_premium' => ['rate', 'employee_share', 'floor', 'ceiling'],
        };
        foreach ($fields as $field) {
            if (! is_string($rule[$field] ?? null) || ! preg_match('/\A(?:0|[1-9][0-9]{0,9})\.[0-9]{2}\z/', $rule[$field])) {
                return false;
            }
            if (bccomp($rule[$field], '0.00', 2) <= 0) {
                return false;
            }
            if (str_contains($field, 'rate') || $field === 'employee_share') {
                if (bccomp($rule[$field], '1.00', 2) > 0) {
                    return false;
                }
            }
        }

        return match ($method) {
            'percentage' => true,
            'tiered_percentage' => bccomp($rule['threshold'], $rule['ceiling'], 2) < 0
                && bccomp($rule['low_rate'], $rule['rate'], 2) <= 0,
            'shared_premium' => bccomp($rule['floor'], $rule['ceiling'], 2) <= 0,
        };
    }

    private function tableTax(string $basis, mixed $table, int $periods): ?array
    {
        if (! is_array($table) || ! array_is_list($table) || count($table) < 2) {
            return null;
        }
        $chosen = null;
        $previous = '-1.00';
        foreach ($table as $row) {
            if (! is_array($row) || ! array_is_list($row) || count($row) !== 3) {
                return null;
            }
            foreach ($row as $value) {
                if (! is_string($value) || ! preg_match('/\A(?:0|[1-9][0-9]{0,9})\.[0-9]{2}\z/', $value)) {
                    return null;
                }
            }
            if (bccomp($row[0], $previous, 2) <= 0 || bccomp($row[2], '1.00', 2) > 0) {
                return null;
            }
            $previous = $row[0];
            // Select against the total basis, without truncating a repeating average.
            if (bccomp($basis, bcmul($row[0], (string) $periods, 2), 2) >= 0) {
                $chosen = $row;
            }
        }
        if ($table[0] !== ['0.00', '0.00', '0.00'] || ! $chosen) {
            return null;
        }
        [$lower, $fixed, $rate] = $chosen;
        // n * (fixed + rate * (basis / n - lower)) is evaluated exactly before rounding.
        $excess = bcsub($basis, bcmul($lower, (string) $periods, 2), 2);
        $liability = bcadd(bcmul($fixed, (string) $periods, 2), bcmul($excess, $rate, 8), 8);

        return ['lower' => $lower, 'fixed' => $fixed, 'rate' => $rate,
            'unrounded_tax' => bcdiv($liability, (string) $periods, 8),
            'unrounded_liability' => $liability];
    }

    public function profileErrors(array $profile): array
    {
        $errors = [];
        foreach (['gsis' => 'percentage', 'pagibig' => 'tiered_percentage', 'philhealth' => 'shared_premium'] as $component => $method) {
            if (! $this->ruleValid($profile['contributions'][$component] ?? null, $method)) {
                $errors['parameters.contributions.'.$component] = 'Enter positive parameters with rates/shares at most 1, an increasing tier, and valid floor/cap limits.';
            }
        }
        foreach (['monthly_brackets', 'annual_brackets'] as $table) {
            if ($this->tableTax('0.00', $profile['tax'][$table] ?? null, 1) === null) {
                $errors['parameters.tax.'.$table] = 'Use ordered brackets starting with 0.00 / 0.00 / 0.00 and rates no greater than 1.';
            }
        }

        return $errors;
    }

    public function calculate(array $core, array $input, array $profile, string $category, string $period): array
    {
        $rules = (new PreviewStatutoryRequest)->rules();
        unset($rules['record_version'], $rules['base_revision'], $rules['demonstration_confirmed']);
        Validator::make($input, $rules)->validate();
        $issues = [];
        $window = ($profile['review_year'] ?? null) === (int) substr($period, 0, 4)
            && isset($profile['category_validation'][$category])
            && ($profile['rounding'] ?? null) === 'half_up_2_at_component_total';
        $contributions = [];
        foreach (['gsis' => 'percentage', 'pagibig' => 'tiered_percentage', 'philhealth' => 'shared_premium'] as $name => $method) {
            $coverage = $input[$name.'_coverage'];
            $basis = $input[$name.'_basis'];
            $rule = $profile['contributions'][$name] ?? null;
            $row = ['coverage' => $coverage, 'entered_basis' => $basis, 'calculation_basis' => null, 'amount' => null];
            if ($coverage === 'not_applicable') {
                $row['amount'] = '0.00';
            } elseif ($coverage !== 'applicable') {
                $this->missing($issues, strtoupper($name).': applicability is unverified.');
            } elseif (! $window || ! $this->ruleValid($rule, $method)) {
                $this->missing($issues, strtoupper($name).': reference rule is missing, invalid or outside the review year/category.');
            } elseif ($basis === null || bccomp($basis, '0.00', 2) <= 0) {
                $this->missing($issues, strtoupper($name).': enter a positive reviewed contribution basis.');
            } else {
                $effective = $basis;
                $rate = $rule['rate'];
                if ($method === 'tiered_percentage') {
                    $effective = bccomp($basis, $rule['ceiling'], 2) > 0 ? $rule['ceiling'] : $basis;
                    $rate = bccomp($basis, $rule['threshold'], 2) <= 0 ? $rule['low_rate'] : $rate;
                } elseif ($method === 'shared_premium') {
                    $effective = bccomp($basis, $rule['floor'], 2) < 0 ? $rule['floor'] : $basis;
                    $effective = bccomp($effective, $rule['ceiling'], 2) > 0 ? $rule['ceiling'] : $effective;
                    $rate = bcmul($rate, $rule['employee_share'], 8);
                }
                $row['calculation_basis'] = $effective;
                $row['applied_rate'] = $rate;
                $row['amount'] = $this->rounded(bcmul($effective, $rate, 8));
            }
            $contributions[$name] = $row;
        }
        $tax = $this->tax($core, $input, $profile, $period, $window, $issues);
        $complete = $issues === [];
        $deductions = $core['totals']['total_deductions'];
        if ($complete) {
            foreach ($contributions as $row) {
                $deductions = bcadd($deductions, $row['amount'], 2);
            }
            $deductions = bcadd($deductions, $tax['withholding'], 2);
        }

        return [
            'engine' => self::VERSION, 'production_ready' => false,
            'status' => $complete ? 'demonstration' : 'incomplete',
            'validation' => 'Client category applicability, bases, procedures and rounding are unverified. Reference scenario only.',
            'contributions' => $contributions, 'tax' => $tax, 'issues' => $issues,
            'total_deductions' => $complete ? $deductions : null,
            'net_before_tax_refund' => $complete ? bcsub($core['totals']['total_earnings'], $deductions, 2) : null,
        ];
    }

    private function tax(array $core, array $input, array $profile, string $period, bool $window, array &$issues): array
    {
        $method = $input['tax_method'];
        $result = ['method' => $method, 'taxable_bonus_excess' => null, 'taxable_compensation' => null,
            'liability' => null, 'withholding' => null, 'credit_or_refund_due' => null];
        if ($method === 'unknown' || ! $window) {
            $this->missing($issues, 'Withholding procedure or reference year/category is unverified.');

            return $result;
        }
        $rule = $profile['tax'] ?? [];
        foreach (['taxable_regular', 'taxable_other', 'qualifying_bonuses', 'prior_withheld'] as $field) {
            if ($input[$field] === null) {
                $this->missing($issues, 'Tax: enter all reviewed compensation, bonus and previous withholding amounts, including explicit zeros.');

                return $result;
            }
        }
        $exemption = $rule['bonus_exemption'] ?? null;
        if (! is_string($exemption) || ! preg_match('/\A(?:0|[1-9][0-9]{0,9})\.[0-9]{2}\z/', $exemption)
            || empty($rule['source']) || empty($rule['monthly_source'])) {
            $this->missing($issues, 'Tax reference parameters or sources are missing.');

            return $result;
        }
        if ($method === 'annual' && substr($period, 5, 2) !== '12') {
            $this->missing($issues, 'Annual reconciliation is supported only for December. Termination treatment requires validation.');

            return $result;
        }
        if ($method === 'monthly_regular' && (bccomp($input['qualifying_bonuses'], '0.00', 2) !== 0
            || bccomp($input['taxable_other'], '0.00', 2) !== 0 || bccomp($input['prior_withheld'], '0.00', 2) !== 0
            || bccomp($core['totals']['bonuses'], '0.00', 2) !== 0 || bccomp($core['totals']['earning_adjustments'], '0.00', 2) !== 0)) {
            $this->missing($issues, 'Monthly regular-only withholding cannot handle bonuses, supplementary earnings or prior withholding. Use a reviewed cumulative or December annual scenario.');

            return $result;
        }
        $periods = $method === 'cumulative' ? $input['elapsed_periods'] : 1;
        if ($periods === null || $periods < 1 || $periods > (int) substr($period, 5, 2)) {
            $this->missing($issues, 'Cumulative calculation needs elapsed monthly payroll periods within this calendar year.');

            return $result;
        }
        // Annual/YTD eligible benefit pool is separate from other fully taxable supplementary pay.
        $excess = bcsub($input['qualifying_bonuses'], $exemption, 2);
        $excess = bccomp($excess, '0.00', 2) < 0 ? '0.00' : $excess;
        $basis = bcadd(bcadd($input['taxable_regular'], $input['taxable_other'], 2), $excess, 2);
        $tableBasis = bcdiv($basis, (string) $periods, 8);
        $bracket = $this->tableTax($basis, $rule[$method === 'annual' ? 'annual_brackets' : 'monthly_brackets'] ?? null, $periods);
        if (! $bracket) {
            $this->missing($issues, 'Withholding reference table is missing or invalid.');

            return $result;
        }
        $liability = $this->rounded($bracket['unrounded_liability']);
        $balance = bcsub($liability, $input['prior_withheld'], 2);

        return array_merge($result, [
            'taxable_bonus_excess' => $excess, 'bonus_exemption' => $exemption,
            'taxable_compensation' => $basis, 'table_basis' => $tableBasis, 'bracket' => $bracket,
            'elapsed_periods' => $periods, 'liability' => $liability, 'prior_withheld' => $input['prior_withheld'],
            'withholding' => bccomp($balance, '0.00', 2) > 0 ? $balance : '0.00',
            'credit_or_refund_due' => bccomp($balance, '0.00', 2) < 0 ? bcsub('0.00', $balance, 2) : '0.00',
        ]);
    }
}
