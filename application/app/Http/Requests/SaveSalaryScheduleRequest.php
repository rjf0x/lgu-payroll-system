<?php

namespace App\Http\Requests;

use App\Enums\PersonnelClassification;
use App\Models\SalarySchedule;
use App\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'personnel_classification' => ['required', Rule::enum(PersonnelClassification::class)],
            'effective_from' => ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'is_demonstration' => ['required', 'boolean'],
        ];
    }
}
