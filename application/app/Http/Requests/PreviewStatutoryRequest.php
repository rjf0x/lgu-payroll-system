<?php

namespace App\Http\Requests;

use App\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewStatutoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('batch'));
    }

    public function rules(): array
    {
        $money = ['nullable', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'];
        $rules = [
            'record_version' => ['required', 'integer', 'min:1'],
            'base_revision' => ['required', 'integer', 'min:0'],
            'configuration_version_id' => ['nullable', 'integer', 'min:1'],
            'tax_method' => ['required', Rule::in(['unknown', 'monthly_regular', 'cumulative', 'annual'])],
            'taxable_regular' => $money,
            'taxable_other' => $money,
            'qualifying_bonuses' => $money,
            'prior_withheld' => $money,
            'elapsed_periods' => ['nullable', 'integer', 'between:1,12'],
            'reference' => ['required', 'string', new Utf8Text, 'max:255'],
            'reason' => ['required', 'string', new Utf8Text, 'max:500'],
            'demonstration_confirmed' => ['required', 'accepted'],
        ];
        foreach (['gsis', 'pagibig', 'philhealth'] as $component) {
            $rules[$component.'_coverage'] = ['required', Rule::in(['unknown', 'applicable', 'not_applicable'])];
            $rules[$component.'_basis'] = $money;
        }

        return $rules;
    }
}
