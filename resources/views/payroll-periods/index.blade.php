@extends('layouts.app')

@section('title', 'Payroll Periods')
@section('heading', 'Payroll Periods')

@section('content')
    <x-page-header description="Manage payroll schedules, review coverage and pay dates, and access associated payroll runs.">
        @can('payroll.prepare')
            <x-button icon="add" variant="primary" href="{{ route('payroll-periods.create') }}">Create Payroll Period</x-button>
        @endcan
    </x-page-header>

    <dl class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="All payroll period statistics">
        @foreach ($statistics as $label => $count)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <dt class="text-sm font-medium text-slate-600">{{ $label }}</dt>
                <dd class="mt-2 text-3xl font-semibold tabular-nums text-blue-700">{{ number_format($count) }}</dd>
            </div>
        @endforeach
    </dl>

    <form method="GET" action="{{ route('payroll-periods.index') }}" class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-form.input name="q" label="Search payroll periods" :value="$filters['q'] ?? ''" placeholder="Search by period name" />
            <x-form.input name="year" label="Coverage year" type="number" min="1900" max="9999" :value="$filters['year'] ?? ''" placeholder="All years" />
            <x-form.select name="payroll_type" label="Payroll type" :selected="$filters['payroll_type'] ?? ''" :options="['' => 'All types', 'MONTHLY' => 'Monthly']" />
            <x-form.select name="status" label="Period status" :selected="$filters['status'] ?? ''" :options="['' => 'All period statuses', 'OPEN' => 'Open', 'CLOSED' => 'Closed']" />
            <x-form.input name="from" label="Coverage from" type="date" :value="$filters['from'] ?? ''" />
            <x-form.input name="to" label="Coverage to" type="date" :value="$filters['to'] ?? ''" />
        </div>
        <p class="mt-3 text-sm text-slate-500">Coverage dates find periods overlapping the selected range. Year refers to the coverage start year. Statistics include all periods.</p>
        <div class="mt-4 flex flex-wrap gap-3">
            <x-button icon="filter" variant="primary" type="submit">Apply filters</x-button>
            <x-button icon="reset" variant="secondary" href="{{ route('payroll-periods.index') }}">Reset</x-button>
        </div>
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
