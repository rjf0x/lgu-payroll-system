<?php

namespace App\Http\Requests;

use App\Models\EmploymentRecord;
use App\Services\EmploymentRecordValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmploymentRecordRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,employee_id'],
            'department_id' => ['required', 'integer', 'exists:departments,department_id'],
            'position_id' => ['required', 'integer', 'exists:positions,position_id'],
            'employment_type_id' => ['required', 'integer', 'exists:employment_types,employment_type_id'],
            'appointment_number' => ['nullable', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'employment_status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $existing = $this->route('employment_record');
            $candidate = $existing instanceof EmploymentRecord ? clone $existing : new EmploymentRecord;
            $candidate->fill($this->only(array_keys($this->rules())));
            foreach (app(EmploymentRecordValidationService::class)->issues($candidate) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });
    }
}
