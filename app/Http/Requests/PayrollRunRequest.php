<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayrollRunRequest extends FormRequest
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
            'payroll_period_id' => ['required', 'integer', 'exists:payroll_periods,payroll_period_id'],
            'run_type' => ['required', Rule::in(['REGULAR', 'SUPPLEMENTAL', 'ADJUSTMENT'])],
        ];
    }
}
