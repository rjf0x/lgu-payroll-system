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
            'earning_type_id' => ['required', 'integer', Rule::exists('earning_types', 'earning_type_id')->where('status', 'ACTIVE')->where('is_recurring', true)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'frequency' => ['required', Rule::in(['MONTHLY'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'earning_type_id.exists' => 'Automatic assignments require an active, recurring earning type. Enter exceptional earnings explicitly in the applicable payroll period.',
            'frequency.in' => 'Only monthly automatic earning assignments are supported. Enter annual or one-time earnings explicitly in the applicable payroll period.',
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
