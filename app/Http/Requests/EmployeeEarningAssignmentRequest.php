<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeEarningAssignmentRequest extends FormRequest
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
            'employment_id' => ['required', 'integer', 'exists:employment_records,employment_id'],
            'earning_type_id' => ['required', 'integer', 'exists:earning_types,earning_type_id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'frequency' => ['required', Rule::in(['MONTHLY', 'ANNUAL', 'ONE_TIME'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('amount') && ! $this->filled('percentage')) {
                $validator->errors()->add('amount', 'Provide either an amount or a percentage.');
            }
        });
    }
}
