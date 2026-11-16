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
            'purpose' => ['required_unless:run_type,REGULAR', 'nullable', 'string', 'max:2000'],
            'replaces_payroll_run_id' => ['nullable', 'integer', 'exists:payroll_runs,payroll_run_id', 'prohibited_unless:run_type,REGULAR'],
            'replacement_reason' => ['required_with:replaces_payroll_run_id', 'nullable', 'string', 'max:2000'],
        ];
    }
}
