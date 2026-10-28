<?php

namespace App\Http\Requests;

use App\Models\SalarySchedule;
use App\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;

class SaveSalaryScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('schedule') ? $this->user()->can('update', $this->route('schedule')) : $this->user()->can('create', SalarySchedule::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', new Utf8Text, 'max:150'],
            'department_scope' => ['bail', 'required', 'string', new Utf8Text, function ($attribute, $value, $fail) {
                if ($value === 'all') {
                    return;
                }
                if (! str_starts_with($value, 'department:') || ! SalarySchedule::departmentOptions()->contains(
                    fn ($name) => SalarySchedule::coverageKey($name) === SalarySchedule::coverageKey(substr($value, strlen('department:')))
                )) {
                    $fail('Select All departments or an existing department.');
                }
            }],
            'personnel_classification' => ['prohibited'],
            'effective_from' => ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'is_demonstration' => ['required', 'boolean'],
        ];
    }
}
