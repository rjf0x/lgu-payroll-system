<?php

namespace App\Http\Requests;

use App\Models\PayrollBatch;
use Illuminate\Foundation\Http\FormRequest;

class CreatePayrollBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PayrollBatch::class);
    }

    public function rules(): array
    {
        return ['period' => ['bail', 'required', 'string', 'date_format:Y-m', 'after_or_equal:1000-01', 'before_or_equal:9999-12'], 'is_demonstration' => ['required', 'boolean']];
    }
}
