<?php

namespace App\Http\Requests;

use App\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSalaryRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employee'));
    }

    public function rules(): array
    {
        $date = ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'];

        return [
            'salary_rate_id' => ['required', 'integer', 'exists:salary_rates,id'],
            'effective_from' => $date,
            'step_basis_date' => $date,
            'change_type' => ['required', Rule::in(['initial', 'step_increment', 'promotion', 'schedule_update'])],
            'position' => ['required', 'string', new Utf8Text, 'max:150'],
            'authority_reference' => ['required', 'string', new Utf8Text, 'max:255'],
            'reason' => ['required', 'string', new Utf8Text, 'max:500'],
            'authorization_confirmed' => ['required', 'accepted'],
        ];
    }
}
