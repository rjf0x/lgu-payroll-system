@extends('layouts.app')

@section('title', 'New Payroll Run')
@section('heading', 'New Payroll Run')

@section('content')
    <form method="POST" action="{{ route('payroll-runs.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" data-submit-feedback>
        @csrf

        <x-form.select
            name="payroll_period_id"
            label="Payroll Period"
            :options="$periods->mapWithKeys(fn ($p) => [$p->payroll_period_id => $p->period_name.' ('.$p->start_date?->format('M d').' – '.$p->end_date?->format('M d, Y').')'])"
            placeholder="Select a period"
            required
        />
        <x-form.select
            name="run_type"
            label="Run Type"
            :options="['REGULAR' => 'Regular', 'SUPPLEMENTAL' => 'Supplemental', 'ADJUSTMENT' => 'Adjustment']"
            selected="REGULAR"
            required
        />

        <x-form.textarea name="purpose" label="Run purpose / supporting reason" maxlength="2000" hint="Required for supplemental and adjustment runs. Add employees explicitly and enter only the additional compensation or correction." />
        <x-form.select name="replaces_payroll_run_id" label="Replace a returned or rejected regular run (optional)" placeholder="No replacement" :options="$replacements->mapWithKeys(fn ($run) => [$run->getKey() => 'Run #'.$run->getKey().' / '.$run->payrollPeriod->period_name.' / '.$run->status])" />
        <x-form.textarea name="replacement_reason" label="Replacement justification" maxlength="2000" hint="Required for a replacement in the same period. The returned or rejected run is retained as cancelled and the replacement needs fresh approval." />

        <p class="text-xs text-slate-500">Regular runs use approved payroll inputs. Non-regular runs require explicit amounts, entitlement references, coverage, and confirmed accounting treatment before submission.</p>

        <div class="flex flex-wrap gap-3">
            <x-button icon="add" variant="primary" type="submit">Create run</x-button>
            <x-button icon="back" variant="secondary" href="{{ route('payroll-runs.index') }}">Cancel</x-button>
        </div>
    </form>
@endsection
