<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPayrollEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('batch'));
    }

    public function rules(): array
    {
        return [
            'record_version' => ['required', 'integer', 'min:1'],
            'employee_ids' => ['required', 'array', 'min:1', 'max:100'],
            'employee_ids.*' => ['bail', 'required', 'integer', 'distinct', 'exists:employees,id'],
        ];
    }
}
