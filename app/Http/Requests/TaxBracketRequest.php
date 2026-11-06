<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaxBracketRequest extends FormRequest
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
            'tax_type' => ['required', 'string', 'max:50'],
            'pay_frequency' => ['required', 'string', 'max:30'],
            'minimum_income' => ['required', 'numeric', 'min:0'],
            'maximum_income' => ['nullable', 'numeric', 'gte:minimum_income'],
            'base_tax' => ['required', 'numeric', 'min:0'],
            'marginal_tax_rate' => ['required', 'numeric', 'between:0,1'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
