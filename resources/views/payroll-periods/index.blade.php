@extends('layouts.app')

@section('title', 'Payroll Periods')
@section('heading', 'Payroll Periods')

@section('content')
    <x-page-header description="Manage payroll schedules, review coverage and pay dates, and access associated payroll runs.">
        @can('payroll.prepare')
            <x-button icon="add" variant="primary" href="{{ route('payroll-periods.create') }}" class="ui-button-compact">Create Payroll Period</x-button>
        @endcan
    </x-page-header>

    <dl class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm" aria-label="All payroll period statistics">
        @foreach ($statistics as $label => $count)
            <div class="flex items-baseline gap-2">
                <dt class="text-muted">{{ $label }}</dt>
                <dd class="font-semibold tabular-nums text-ink">{{ number_format($count) }}</dd>
            </div>
        @endforeach
    </dl>

    <form method="GET" action="{{ route('payroll-periods.index') }}" aria-label="Payroll period filters" class="ui-filter-toolbar mb-5 rounded-lg border border-slate-200 bg-surface p-3">
        <div class="flex flex-wrap items-end gap-2">
            <div class="w-full min-w-0 sm:min-w-40 sm:flex-1 sm:basis-40">
                <x-form.input name="q" aria-label="Search payroll periods" type="search" :value="$filters['q'] ?? ''" placeholder="Search periods" />
            </div>
            <div class="w-20">
                <x-form.input name="year" aria-label="Coverage year" aria-describedby="payroll-period-filter-help" type="number" min="1900" max="9999" :value="$filters['year'] ?? ''" placeholder="Year" />
            </div>
            <div class="min-w-28 flex-1 sm:w-32 sm:flex-none">
                <x-form.select name="status" aria-label="Period status" :selected="$filters['status'] ?? ''" :options="['' => 'All statuses', 'OPEN' => 'Open', 'CLOSED' => 'Closed']" />
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <x-button icon="filter" variant="primary" type="submit" aria-label="Apply filters">Apply</x-button>
                <x-button icon="reset" variant="secondary" href="{{ route('payroll-periods.index') }}">Reset</x-button>
            </div>
        </div>
        @php($hasExtraFilters = ! empty($filters['payroll_type']) || ! empty($filters['from']) || ! empty($filters['to']))
        <details id="payroll-period-extra-filters" class="group mt-2" @if ($hasExtraFilters) open @endif>
            <summary class="ui-button ui-button-secondary list-none [&::-webkit-details-marker]:hidden">
                <span>More filters</span>
                @if ($hasExtraFilters)
                    <span class="rounded bg-brand-soft px-1.5 text-xs font-semibold text-brand-ink">Active</span>
                @endif
                <svg class="size-4 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m5 7.5 5 5 5-5" /></svg>
            </summary>
            <div class="mt-3 grid grid-cols-1 items-end gap-3 border-t border-slate-200 pt-3 sm:grid-cols-3">
                <x-form.select name="payroll_type" label="Type" aria-label="Payroll type" :selected="$filters['payroll_type'] ?? ''" :options="['' => 'All types', 'MONTHLY' => 'Monthly']" />
                <x-form.input name="from" label="From" aria-label="Coverage from" aria-describedby="payroll-period-filter-help" type="date" :value="$filters['from'] ?? ''" />
                <x-form.input name="to" label="To" aria-label="Coverage to" aria-describedby="payroll-period-filter-help" type="date" :value="$filters['to'] ?? ''" />
            </div>
            <details id="payroll-period-coverage-help" class="mt-2">
                <summary class="ui-button ui-button-ghost list-none px-2 text-xs [&::-webkit-details-marker]:hidden">
                    <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10" cy="10" r="7.5" /><path stroke-linecap="round" d="M8 7.5a2 2 0 1 1 3.5 1.3c-.7.5-1.5 1-1.5 2.2M10 14h.01" /></svg>
                    <span>How coverage works</span>
                </summary>
                <p id="payroll-period-filter-help" class="mt-2 max-w-2xl text-xs leading-5 text-muted">Dates match overlapping coverage; year uses the start date. Metrics include all periods.</p>
            </details>
        </details>
    </form>

    <div class="min-w-0">
        <h2 class="mb-2 text-lg font-semibold text-slate-900">Payroll schedules</h2>
        <p class="mb-4 text-sm text-slate-500">Newest coverage first. Open and Closed describe the period; Processing and Finalized describe its payroll runs.</p>
        <x-results :items="$periods" />
        <x-table label="Payroll Periods records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Period</th>
                    <th scope="col" class="px-4 py-3 text-left">Type</th>
                    <th scope="col" class="px-4 py-3 text-left">Coverage</th>
                    <th scope="col" class="px-4 py-3 text-left">Pay Date</th>
                    <th scope="col" class="px-4 py-3 text-center">Period status</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Runs</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($periods as $period)
                    <tr>
                        <td class="px-4 py-3 font-medium ui-table-primary">{{ $period->period_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ ucfirst(strtolower($period->payroll_type)) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $period->start_date?->format('M d, Y') }} – {{ $period->end_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $period->pay_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-center"><x-status :value="$period->status" /></td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span class="font-semibold tabular-nums">{{ $period->runs_count }}</span>
                            @if ($period->processing_runs_count || $period->finalized_runs_count)
                                <div class="ui-table-secondary flex justify-end gap-3">
                                    @if ($period->processing_runs_count)
                                        <span class="text-sky-800">Processing: {{ $period->processing_runs_count }} runs</span>
                                    @endif
                                    @if ($period->finalized_runs_count)
                                        <span class="text-emerald-800">Finalized: {{ $period->finalized_runs_count }} runs</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for '.$period->period_name">
                                @include('payroll-periods._actions')
                            </x-action-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No payroll periods match your filters. Adjust the filters or create a payroll period.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $periods->links() }}</div>
@endsection
