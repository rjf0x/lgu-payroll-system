<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EarningTypeRequest extends FormRequest
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
            'earning_name' => ['required', 'string', 'max:150'],
            'earning_category' => ['required', 'string', 'max:100'],
            'computation_type' => ['required', Rule::in(['MANUAL', 'FIXED', 'PERCENTAGE'])],
            'is_taxable' => ['required', 'boolean'],
            'is_recurring' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }
}
