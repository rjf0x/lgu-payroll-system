<?php

namespace App\Http\Requests;

use App\Enums\PersonnelClassification;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('employee')
            ? $this->user()->can('update', $this->route('employee'))
            : $this->user()->can('create', Employee::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('employee_number'))) {
            $this->merge(['employee_number' => strtoupper(trim($this->input('employee_number')))]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'employee_number' => ['bail', 'required', 'string', 'max:50', 'regex:~\A[A-Z0-9][A-Z0-9._/-]*\z~', Rule::unique('employees')->ignore($this->route('employee'))],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'position' => ['required', 'string', 'max:150'],
            'office' => ['required', 'string', 'max:150'],
            'personnel_classification' => ['required', Rule::enum(PersonnelClassification::class)],
            'employment_start_date' => ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'is_active' => ['required', 'boolean'],
        ];
        if ($this->route('employee')) {
            $rules['record_version'] = ['required', 'integer', 'min:1'];
        }

        return $rules;
    }
}
