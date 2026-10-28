<?php

namespace App\Http\Requests;

use App\Enums\PersonnelClassification;
use App\Models\PayrollRuleVersion;
use App\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePayrollRuleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PayrollRuleVersion::class);
    }

    public function rules(): array
    {
        $money = ['required', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'];
        $source = ['required', 'string', new Utf8Text, 'max:255'];
        $rules = [
            'submission_key' => ['required', 'uuid'], 'record_version' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', new Utf8Text, 'max:150'],
            'personnel_classification' => ['bail', 'required', 'string', Rule::in(['all', ...array_column(PersonnelClassification::cases(), 'value')])],
            // Older forms omitted department; their original scope was all departments.
            'department_scope' => ['bail', 'sometimes', 'required', 'string', new Utf8Text, function ($attribute, $value, $fail) {
                if ($value === 'all') {
                    return;
                }
                if (! str_starts_with($value, 'department:') || ! PayrollRuleVersion::departmentOptions()->contains(
                    fn ($name) => mb_strtolower(trim($name)) === mb_strtolower(trim(substr($value, strlen('department:'))))
                )) {
                    $fail('Select All departments or an existing department.');
                }
            }],
            'is_demonstration' => ['required', 'boolean'],
            'effective_from' => ['required', 'string', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'change_reason' => ['required', 'string', new Utf8Text, 'max:500'],
            'parameters' => ['required', 'array:contributions,tax'],
            'parameters.contributions' => ['required', 'array:gsis,pagibig,philhealth'],
            'parameters.tax' => ['required', 'array:bonus_exemption,source,monthly_source,monthly_brackets,annual_brackets'],
            'parameters.tax.bonus_exemption' => $money,
            'parameters.tax.source' => $source, 'parameters.tax.monthly_source' => $source,
        ];
        foreach (['gsis' => ['rate'], 'pagibig' => ['threshold', 'low_rate', 'rate', 'ceiling'], 'philhealth' => ['rate', 'employee_share', 'floor', 'ceiling']] as $component => $fields) {
            $prefix = 'parameters.contributions.'.$component;
            $rules[$prefix] = ['required', 'array:'.implode(',', [...$fields, 'source', 'scope'])];
            foreach ($fields as $field) {
                $rules[$prefix.'.'.$field] = $money;
            }
            $rules[$prefix.'.source'] = $source;
            $rules[$prefix.'.scope'] = $source;
        }
        foreach (['monthly_brackets', 'annual_brackets'] as $table) {
            $prefix = 'parameters.tax.'.$table;
            $rules[$prefix] = ['required', 'array', 'list', 'size:6'];
            $rules[$prefix.'.*'] = ['required', 'array', 'list', 'size:3'];
            $rules[$prefix.'.*.*'] = $money;
        }

        return $rules;
    }
}
