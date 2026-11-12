@extends('layouts.app')

@section('title', 'Edit Payroll Detail')
@section('heading', 'Payroll Detail - '.$detail->employee_name_snapshot)

@section('content')
    <form method="POST" action="{{ route('payroll-details.update', [$run, $detail]) }}" class="space-y-5" data-submit-feedback>
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <x-card heading="Employee">
                <dl class="space-y-3 text-sm">
                    <div><dt class="font-semibold">Position</dt><dd>{{ $detail->position_snapshot }}</dd></div>
                    <div><dt class="font-semibold">Basic pay</dt><dd>PHP {{ number_format((float) $detail->basic_pay, 2) }}</dd></div>
                    <div><dt class="font-semibold">Pay basis</dt><dd>{{ str_replace('_', ' ', $detail->pay_basis_snapshot) }}</dd></div>
                </dl>
                <div class="mt-5 space-y-4">
                    <x-form.input name="payable_days" label="Approved payable days" type="number" step="0.01" min="0" :max="$detail->pay_basis_snapshot === 'DAILY' ? $run->payrollPeriod->start_date->diffInDays($run->payrollPeriod->end_date) + 1 : 22" :value="$detail->payable_days" required hint="Monthly pay uses a 22-day equivalent; daily pay uses the approved number of payable days. Enter zero explicitly when no pay is due." />
                    <x-form.input name="input_reference" label="Approved input reference / reason" :value="$detail->input_reference" maxlength="255" required hint="Identify the approved summary or authorization supporting these inputs and adjustments." />
                    <p class="text-sm text-muted">Reflect unpaid absences in payable days. Do not deduct the same absence again under Deductions.</p>
                    <label class="flex min-h-11 items-start gap-3 text-sm"><input type="checkbox" name="inputs_confirmed" value="1" @checked(old('inputs_confirmed')) required class="mt-1 h-5 w-5 shrink-0"><span>I confirm these payable days and adjustments match the approved source, including when no adjustments are needed.</span></label>
                </div>
                <p class="mt-4 text-muted">Statutory contributions and withholding tax are computed automatically when you save.</p>
            </x-card>
            <x-card heading="Earnings">
                <div class="space-y-4">
                    @foreach ($earningTypes as $earningType)
                        <x-form.input :name="'earnings['.$earningType->earning_type_id.']'" :id="'earning-'.$earningType->earning_type_id" :label="$earningType->earning_name" type="number" step="0.01" min="0" :hint="$earningType->is_taxable ? 'Taxable earning' : null" :value="$earningAmounts[$earningType->earning_type_id] ?? ''" />
                    @endforeach
                </div>
            </x-card>
            <x-card heading="Deductions">
                <div class="space-y-4">
                    @foreach ($deductionTypes as $deductionType)
                        <x-form.input :name="'deductions['.$deductionType->deduction_type_id.']'" :id="'deduction-'.$deductionType->deduction_type_id" :label="$deductionType->deduction_name" type="number" step="0.01" min="0" :value="$deductionAmounts[$deductionType->deduction_type_id] ?? ''" />
                    @endforeach
                    <x-form.input name="lbp_loan_deduction" label="LBP Loan Deduction (after NTHP)" type="number" step="0.01" min="0" :value="$detail->lbp_loan_deduction" />
                </div>
            </x-card>
        </div>
        <div class="flex flex-wrap gap-3">
            <x-button icon="save" type="submit" variant="primary">Save &amp; recompute</x-button>
            <x-button icon="back" :href="route('payroll-runs.show', $run)">Cancel</x-button>
        </div>
    </form>
@endsection
