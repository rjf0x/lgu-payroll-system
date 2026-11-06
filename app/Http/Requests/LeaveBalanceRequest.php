<?php

namespace App\Http\Requests;

use App\Models\LeaveBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveBalanceRequest extends FormRequest
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
        $balance = $this->route('leave_balance');
        $balanceId = $balance instanceof LeaveBalance ? $balance->leave_balance_id : null;

        return [
            'employee_id' => ['required', 'integer', 'exists:employees,employee_id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,leave_type_id'],
            'leave_year' => [
                'required', 'integer', 'between:2000,2100',
                Rule::unique('leave_balances', 'leave_year')
                    ->where(fn ($query) => $query
                        ->where('employee_id', $this->input('employee_id'))
                        ->where('leave_type_id', $this->input('leave_type_id')))
                    ->ignore($balanceId, 'leave_balance_id'),
            ],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'total_credits' => ['required', 'numeric', 'min:0'],
        ];
    }
}
