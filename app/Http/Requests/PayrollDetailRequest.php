<?php

namespace App\Http\Requests;

use App\Models\CompensationProfile;
use App\Models\DeductionType;
use App\Models\EarningType;
use Illuminate\Foundation\Http\FormRequest;
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
        $maxDays = $detail->pay_basis_snapshot === CompensationProfile::BASIS_DAILY
            ? $this->route('payroll_run')->payrollPeriod->start_date->diffInDays($this->route('payroll_run')->payrollPeriod->end_date) + 1
            : 22;

        return [
            'payable_days' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$maxDays],
            'input_reference' => ['nullable', 'string', 'max:255'],
            'inputs_confirmed' => ['required', 'accepted'],
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
