@extends('layouts.app')

@section('title', 'New Payroll Run')
@section('heading', 'New Payroll Run')

@section('content')
    <form method="POST" action="{{ route('payroll-runs.store') }}" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
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

        <p class="text-xs text-slate-500">The run is created as a draft; compute it to generate payroll details.</p>

        <div class="flex gap-3">
            <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Create run</button>
            <a href="{{ route('payroll-runs.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
@endsection
