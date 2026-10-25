<?php

namespace App\Http\Requests;

use App\Rules\Utf8Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePayrollInputsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('batch'));
    }

    public function rules(): array
    {
        $money = ['bail', 'required', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'];
        $label = ['required', 'string', new Utf8Text, 'max:100', 'distinct:ignore_case'];
        $days = (int) $this->route('batch')->period_start->daysInMonth;

        return [
            'record_version' => ['required', 'integer', 'min:1'],
            'allowances' => ['sometimes', 'array', 'max:30'],
            'allowances.*' => ['array:label,amount,is_applicable'],
            'allowances.*.label' => $label,
            'allowances.*.amount' => $money,
            'allowances.*.is_applicable' => ['required', 'boolean'],
            'bonuses' => ['sometimes', 'array', 'max:30'],
            'bonuses.*' => ['array:label,amount'],
            'bonuses.*.label' => $label,
            'bonuses.*.amount' => $money,
            'loans' => ['sometimes', 'array', 'max:30'],
            'loans.*' => ['array:label,amount'],
            'loans.*.label' => $label,
            'loans.*.amount' => $money,
            'adjustments' => ['sometimes', 'array', 'max:30'],
            'adjustments.*' => ['array:label,direction,original_amount,adjusted_amount,reason'],
            'adjustments.*.label' => $label,
            'adjustments.*.direction' => ['required', Rule::in(['earning', 'deduction'])],
            'adjustments.*.original_amount' => $money,
            'adjustments.*.adjusted_amount' => $money,
            'adjustments.*.reason' => ['required', 'string', new Utf8Text, 'max:500'],
            'attendance' => ['required', 'array:absence_days,undertime_minutes,notes'],
            'attendance.absence_days' => ['bail', 'required', 'string', 'regex:/\A[0-9]{1,2}(?:\.[0-9]{1,2})?\z/', 'numeric', 'between:0,'.$days],
            'attendance.undertime_minutes' => ['required', 'integer', 'between:0,'.($days * 1440)],
            'attendance.notes' => ['nullable', 'string', new Utf8Text, 'max:500'],
            'authority_reference' => ['required', 'string', new Utf8Text, 'max:255'],
            'change_reason' => ['required', 'string', new Utf8Text, 'max:500'],
            'authorization_confirmed' => ['required', 'accepted'],
        ];
    }
}
