<?php

namespace App\Http\Requests;

use App\Models\PayrollRun;
use App\Services\PayrollApprovalService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PayrollDecisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $run = $this->route('payroll_run');
        $permission = match ($this->route()->getName()) {
            'payroll-runs.approve' => 'payroll.approve',
            'payroll-runs.reject' => 'payroll.reject',
            default => 'payroll.review',
        };

        return $run instanceof PayrollRun && $this->user()->can($permission)
            && app(PayrollApprovalService::class)->isIndependentReviewer($run, $this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'remarks' => [$this->routeIs('payroll-runs.approve') ? 'nullable' : 'required', 'string', 'max:2000'],
        ];
    }
}
