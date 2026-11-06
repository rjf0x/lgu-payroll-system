<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContributionRateRequest extends FormRequest
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
            'contribution_type' => ['required', 'string', 'max:100'],
            'minimum_compensation' => ['required', 'numeric', 'min:0'],
            'maximum_compensation' => ['nullable', 'numeric', 'gte:minimum_compensation'],
            'employee_rate' => ['required', 'numeric', 'between:0,1'],
            'employer_rate' => ['required', 'numeric', 'between:0,1'],
            'employee_fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'employer_fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
