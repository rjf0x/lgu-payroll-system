@extends('layouts.app')

@section('title', 'Edit Payroll Detail')
@section('heading', 'Payroll Detail - '.$detail->employee_name_snapshot)

@section('content')
    @if ($accountingPolicyRequired)
        <x-alert variant="warning">Accounting policy confirmation required. These are provisional amounts; taxes and statutory contributions have not been established for this transaction. Submission, approval, and finalization remain blocked until the Municipal Accounting Office confirms the calculation method.</x-alert>
    @endif
    @if ($eligibilityIssue)
        <x-alert variant="warning">{{ $eligibilityIssue }}</x-alert>
    @endif
    <form method="POST" action="{{ route('payroll-details.update', [$run, $detail]) }}" class="payroll-detail-form ui-compact-form flex flex-col gap-4" data-payroll-detail-form data-submit-feedback>
        @csrf
        @method('PUT')
        <section aria-labelledby="employee-inputs-heading" class="rounded-xl border border-slate-200 bg-surface p-4 shadow-sm">
            <h2 id="employee-inputs-heading" class="text-sm font-semibold text-ink">Employee &amp; approved inputs</h2>
            @if ($requiresEligibilityException)
                <div class="mt-3">
                    <x-form.textarea name="eligibility_reason" label="Retain employee: eligibility justification" :value="$detail->eligibility_reason" maxlength="2000" required hint="Document the employment dates, earned compensation, and authorization supporting payment for this coverage period. Saving confirms eligibility and recomputes current inputs." />
                </div>
            @endif
            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-b border-slate-200 pb-3 text-sm sm:grid-cols-4">
                <div class="col-span-2 min-w-0"><dt class="text-xs text-muted">Position</dt><dd class="break-words font-semibold">{{ $detail->position_snapshot }}</dd></div>
                <div><dt class="text-xs text-muted">Basic pay · PHP</dt><dd class="font-semibold tabular-nums">{{ number_format((float) $detail->basic_pay, 2) }}</dd></div>
                <div><dt class="text-xs text-muted">Pay basis</dt><dd class="font-semibold">{{ str_replace('_', ' ', $detail->pay_basis_snapshot) }}</dd></div>
            </dl>
            @if ($run->run_type !== 'REGULAR')
                <input type="hidden" name="payable_days" value="0">
                <div class="mt-3 space-y-3">
                    <x-form.input name="coverage_start" label="Entitlement coverage start" type="date" :value="$detail->coverage_start?->toDateString() ?? $run->payrollPeriod->start_date->toDateString()" required />
                    <x-form.input name="coverage_end" label="Entitlement coverage end" type="date" :value="$detail->coverage_end?->toDateString() ?? $run->payrollPeriod->end_date->toDateString()" required />
                    <x-form.input name="compensation_reference" label="Supporting entitlement reference" :value="$detail->compensation_reference" maxlength="255" required hint="Use the same reference for the same entitlement. A different reference must identify separate authorized compensation." />
                    <x-form.textarea name="compensation_reason" label="Additional payment or correction justification" :value="$detail->compensation_reason" maxlength="2000" required />
                    <x-form.textarea name="accounting_basis" label="Accounting authorization and financial treatment" :value="$detail->accounting_basis" maxlength="2000" required hint="Identify the supporting accounting decision for these amounts, taxes, and contributions. This does not replace the required configured accounting policy." />
                    <x-form.textarea name="overlap_justification" label="Separate entitlement justification (when a component overlaps)" :value="$detail->overlap_justification" maxlength="2000" hint="Explain why this is additional authorized compensation for the same coverage, rather than a repeat payment." />
                    @if ($run->run_type === 'ADJUSTMENT')
                        <x-form.select name="original_payroll_detail_id" label="Finalized original payroll detail" :selected="$detail->original_payroll_detail_id" required placeholder="Choose the original detail" :options="$originalDetails->mapWithKeys(fn ($original) => [$original->getKey() => 'Detail #'.$original->getKey().' / Run #'.$original->payroll_run_id.' / '.$original->payrollRun->payrollPeriod->period_name])" />
                        <x-form.input name="adjustment_basic_pay" label="Authorized basic salary adjustment only (PHP)" type="number" step="0.01" min="0" :value="$detail->basic_pay" hint="Enter only the positive difference. Other earning corrections belong in the earning components below. Automatic recoveries are unsupported." />
                    @endif
                </div>
            @else
            <div class="mt-3 grid items-start gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                <div>
                    <x-form.input name="payable_days" label="Approved payable days" type="number" step="0.01" min="0" :max="$detail->maximumPayableDays($run->payrollPeriod)" :value="$detail->payable_days" required inputmode="decimal" class="max-w-36 text-right tabular-nums" aria-describedby="approved-input-guidance" />
                </div>
                <x-form.input name="input_reference" label="Approval reference / reason (optional)" :value="$detail->input_reference" maxlength="255" aria-describedby="approved-input-guidance" />
            </div>
            <details class="group mt-2 text-sm text-muted">
                <summary class="inline-flex min-h-9 items-center gap-2 rounded font-medium text-brand underline decoration-dotted underline-offset-4">
                    <svg class="h-3.5 w-3.5 shrink-0 group-open:rotate-90" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m7 4 6 6-6 6" /></svg>
                    Approved input guidance
                </summary>
                <div id="approved-input-guidance" class="mt-1 space-y-1">
                    <p>Monthly pay uses a 22-day equivalent; daily pay uses the approved number of payable days. Enter zero explicitly when no pay is due.</p>
                    <p>You may add the approved summary or authorization supporting these inputs and adjustments.</p>
                    <p>Reflect unpaid absences in payable days. Do not deduct the same absence again under Deductions.</p>
                </div>
            </details>
            @endif
        </section>

        <section aria-labelledby="payroll-adjustments-heading" class="min-w-0 rounded-xl border border-slate-200 bg-surface shadow-sm">
            <div class="border-b border-slate-200 px-4 pt-3">
                <h2 id="payroll-adjustments-heading" class="text-sm font-semibold">Payroll adjustments</h2>
                <p class="mt-1 text-xs text-muted">{{ $accountingPolicyRequired ? 'Amounts remain provisional until accounting confirms tax and contribution treatment.' : 'Statutory contributions and withholding tax use the configured calculation method when you save.' }} All amounts are in PHP.</p>
                <div hidden data-payroll-tabs aria-label="Payroll adjustment categories" class="mt-2 flex gap-1">
                    <button type="button" id="earnings-tab" data-payroll-tab="earnings" aria-controls="earnings-panel" class="payroll-adjustment-tab">Earnings</button>
                    <button type="button" id="deductions-tab" data-payroll-tab="deductions" aria-controls="deductions-panel" class="payroll-adjustment-tab">Deductions</button>
                </div>
            </div>

            @include('payroll-details._adjustment-panel', ['section' => 'earnings', 'types' => $earningTypes, 'amounts' => $earningAmounts])
            @include('payroll-details._adjustment-panel', ['section' => 'deductions', 'types' => $deductionTypes, 'amounts' => $deductionAmounts])
        </section>

        <x-audit-change-context />

        <div class="px-1">
            <label for="inputs_confirmed" class="flex min-h-11 items-start gap-2 text-sm">
                <input id="inputs_confirmed" type="checkbox" name="inputs_confirmed" value="1" @checked(old('inputs_confirmed')) required aria-invalid="{{ $errors->has('inputs_confirmed') ? 'true' : 'false' }}" @if ($errors->has('inputs_confirmed')) aria-describedby="inputs_confirmed-error" @endif class="mt-0.5 h-5 w-5 shrink-0">
                <span>I confirm these payable days and adjustments match the approved source, including when no adjustments are needed.</span>
            </label>
            <x-form.feedback name="inputs_confirmed" id="inputs_confirmed" />
        </div>

        <div class="payroll-detail-actions sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-x-5 gap-y-3 rounded-xl border border-slate-300 bg-surface p-3 shadow-sm" aria-label="Payroll summary and actions">
            <div class="min-w-0">
                <p class="text-xs font-semibold text-muted">Last computed · PHP</p>
                <dl class="mt-1 flex flex-wrap gap-x-5 gap-y-1 text-xs">
                    <div class="hidden sm:block"><dt class="text-muted">Gross pay</dt><dd class="text-sm font-semibold tabular-nums">{{ number_format((float) $detail->gross_pay, 2) }}</dd></div>
                    <div class="hidden sm:block"><dt class="text-muted">Deductions + LBP</dt><dd class="text-sm font-semibold tabular-nums">{{ number_format((float) $detail->total_deductions + (float) $detail->lbp_loan_deduction, 2) }}</dd></div>
                    <div><dt class="text-brand-ink">Net pay</dt><dd class="text-sm font-bold tabular-nums text-brand-ink">{{ number_format((float) $detail->net_pay, 2) }}</dd></div>
                </dl>
                <p data-payroll-save-status role="status" class="mt-1 text-xs text-muted">{{ session()->hasOldInput() ? 'Unsaved changes. Totals update when you save.' : 'Totals update when you save.' }}</p>
            </div>
            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                <x-button icon="save" type="submit" variant="primary" class="flex-1 sm:flex-none" data-submitting-label="Saving & recomputing…">Save &amp; Recompute</x-button>
                <x-button icon="back" :href="route('payroll-runs.show', $run)">Cancel</x-button>
            </div>
        </div>
    </form>
    <section class="mt-6 rounded-xl border border-slate-200 bg-surface p-4" aria-labelledby="remove-employee-heading">
        <h2 id="remove-employee-heading" class="text-sm font-semibold">Remove employee from this run</h2>
        <p class="mt-2 text-sm text-muted">Confirm that compensation is not payable in this run. Removal deletes this editable payroll detail and its financial rows.</p>
        <form method="POST" action="{{ route('payroll-details.destroy', [$run, $detail]) }}" class="mt-3 space-y-3" onsubmit="return confirm('Remove this employee and their payroll detail from this run?')">
            @csrf
            @method('DELETE')
            <x-form.textarea name="reason" label="Removal reason" required maxlength="2000" />
            <x-button type="submit" variant="danger">Remove employee</x-button>
        </form>
    </section>
@endsection
