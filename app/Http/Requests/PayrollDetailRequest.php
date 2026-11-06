<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'lbp_loan_deduction' => ['nullable', 'numeric', 'min:0'],
            'earnings' => ['array'],
            'earnings.*' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['array'],
            'deductions.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
