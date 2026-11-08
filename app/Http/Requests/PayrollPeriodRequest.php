<?php

namespace App\Http\Requests;

use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PayrollPeriodRequest extends FormRequest
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
        return [
            'period_name' => ['required', 'string', 'max:100'],
            'payroll_type' => ['required', Rule::in(['MONTHLY'])],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'pay_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['OPEN', 'CLOSED'])],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['start_date', 'end_date', 'payroll_type'])) {
                return;
            }

            $period = $this->route('payroll_period');
            $duplicate = PayrollPeriod::query()
                ->where('payroll_type', $this->input('payroll_type'))
                ->whereDate('start_date', $this->input('start_date'))
                ->whereDate('end_date', $this->input('end_date'))
                ->when($period instanceof PayrollPeriod, fn ($query) => $query->whereKeyNot($period->getKey()))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('end_date', 'A payroll period with these coverage dates and payroll type already exists.');
            }
        }];
    }
}
