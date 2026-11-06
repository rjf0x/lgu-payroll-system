<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeductionTypeRequest extends FormRequest
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
            'deduction_name' => ['required', 'string', 'max:150'],
            'deduction_category' => ['required', 'string', 'max:100'],
            'computation_type' => ['required', Rule::in(['MANUAL', 'FIXED', 'PERCENTAGE', 'COMPUTED'])],
            'is_statutory' => ['required', 'boolean'],
            'is_pre_tax' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }
}
