<?php

namespace App\Http\Requests;

use App\Models\DeductionType;
use App\Models\EarningType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PayrollDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $detail = $this->route('payroll_detail');
        $maxDays = $detail->maximumPayableDays($this->route('payroll_run')->payrollPeriod);
        $run = $this->route('payroll_run');
        $nonRegular = $run->run_type !== 'REGULAR';

        return [
            'payable_days' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.($nonRegular ? 0 : $maxDays)],
            'coverage_start' => [Rule::requiredIf($nonRegular), 'nullable', Rule::prohibitedIf(! $nonRegular), 'date_format:Y-m-d', 'after_or_equal:'.$run->payrollPeriod->start_date->toDateString()],
            'coverage_end' => [Rule::requiredIf($nonRegular), 'nullable', Rule::prohibitedIf(! $nonRegular), 'date_format:Y-m-d', 'after_or_equal:coverage_start', 'before_or_equal:'.$run->payrollPeriod->end_date->toDateString()],
            'compensation_reference' => [Rule::requiredIf($nonRegular), 'nullable', 'string', 'max:255'],
            'compensation_reason' => [Rule::requiredIf($nonRegular), 'nullable', 'string', 'max:2000'],
            'accounting_basis' => [Rule::requiredIf($nonRegular), 'nullable', 'string', 'max:2000'],
            'overlap_justification' => ['nullable', 'string', 'max:2000'],
            'original_payroll_detail_id' => [Rule::requiredIf($run->run_type === 'ADJUSTMENT'), 'nullable', Rule::prohibitedIf($run->run_type !== 'ADJUSTMENT'), 'integer', 'exists:payroll_details,payroll_detail_id'],
            'adjustment_basic_pay' => ['nullable', Rule::prohibitedIf($run->run_type !== 'ADJUSTMENT'), 'numeric', 'decimal:0,2', 'min:0'],
            'input_reference' => ['nullable', 'string', 'max:255'],
            'inputs_confirmed' => ['required', 'accepted'],
            'eligibility_reason' => ['nullable', 'string', 'max:2000'],
            'lbp_loan_deduction' => ['nullable', 'numeric', 'min:0'],
            'earnings' => ['array'],
            'earnings.*' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['array'],
            'deductions.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['earnings', 'deductions'] as $field) {
                $values = $this->input($field, []);
                if (! is_array($values) || $values === []) {
                    continue;
                }
                $ids = array_map('strval', array_keys($values));
                foreach ($ids as $id) {
                    if (! ctype_digit($id)) {
                        $validator->errors()->add($field, 'Use valid numeric type identifiers.');

                        continue 2;
                    }
                }
                $query = $field === 'earnings'
                    ? EarningType::where('status', 'ACTIVE')->whereIn('earning_type_id', $ids)
                    : DeductionType::where('status', 'ACTIVE')->where('is_statutory', false)->where('deduction_category', '!=', 'LBP')->whereIn('deduction_type_id', $ids);
                $valid = $query->pluck($field === 'earnings' ? 'earning_type_id' : 'deduction_type_id')->map(fn ($id): string => (string) $id)->all();
                if (array_diff($ids, $valid) !== []) {
                    $validator->errors()->add($field, $field === 'earnings' ? 'Choose active earning types only.' : 'Choose active, non-statutory deductions outside the LBP category.');
                }
            }
        });
    }
}
