@extends('layouts.app')

@section('title', 'Payroll Period Details')
@section('heading', 'Payroll Period Details')

@section('content')
    <x-page-header description="Review this schedule and the individual status of each associated payroll run.">
        <x-button icon="back" variant="secondary" href="{{ route('payroll-periods.index') }}" class="ui-button-compact">Back to Payroll Periods</x-button>
    </x-page-header>
    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="period-heading">
        <h2 id="period-heading" class="mb-4 text-xl font-semibold">{{ $period->period_name }}</h2>
        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-slate-500">Payroll type</dt><dd class="mt-1 font-medium">{{ ucfirst(strtolower($period->payroll_type)) }}</dd></div>
            <div><dt class="text-sm text-slate-500">Coverage period</dt><dd class="mt-1 font-medium">{{ $period->start_date->format('M d, Y') }} &ndash; {{ $period->end_date->format('M d, Y') }}</dd></div>
            <div><dt class="text-sm text-slate-500">Scheduled pay date</dt><dd class="mt-1 font-medium">{{ $period->pay_date->format('M d, Y') }}</dd></div>
            <div><dt class="text-sm text-slate-500">Period status</dt><dd class="mt-1"><x-status :value="$period->status" /></dd></div>
        </dl>
        <p class="my-4 text-sm text-slate-500">Closing a period blocks new runs and preserves existing transactions. Period details are locked once runs exist.</p>
        @include('payroll-periods._actions')
    </section>
    <h2 class="mb-3 text-lg font-semibold">Associated payroll runs ({{ $period->runs_count }})</h2>
    <x-results :items="$runs" />
    <x-table label="Associated payroll runs">
        <thead><tr><th scope="col" class="ui-table-primary">Run</th><th scope="col">Type</th><th scope="col" class="text-center">Run status</th><th scope="col" class="text-right ui-table-actions">Actions</th></tr></thead>
        <tbody>
            @forelse ($runs as $run)
                <tr><td class="ui-table-primary">#{{ $run->run_number }}</td><td>{{ ucfirst(strtolower($run->run_type)) }}</td><td class="text-center"><x-status :value="$run->status" /></td><td class="text-right ui-table-actions"><x-action-menu :label="'Actions for payroll run #'.$run->run_number"><x-button icon="view" :href="route('payroll-runs.show', $run)">View run #{{ $run->run_number }}</x-button></x-action-menu></td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-slate-500">No payroll runs have been created for this period.</td></tr>
            @endforelse
        </tbody>
    </x-table>
    <div class="mt-4">{{ $runs->links() }}</div>
@endsection
