@extends('layouts.app')

@section('title', 'Payroll Periods')
@section('heading', 'Payroll Periods')

@section('content')
    <x-page-header description="Manage payroll schedules, review coverage and pay dates, and access associated payroll runs.">
        @can('payroll.prepare')
            <a href="{{ route('payroll-periods.create') }}" class="ui-button ui-button-primary">Create Payroll Period</a>
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
            <button type="submit" class="ui-button ui-button-primary">Apply filters</button>
            <a href="{{ route('payroll-periods.index') }}" class="ui-button ui-button-secondary">Reset</a>
        </div>
    </form>

    <div class="min-w-0">
        <h2 class="mb-2 text-lg font-semibold text-slate-900">Payroll schedules</h2>
        <p class="mb-4 text-sm text-slate-500">Newest coverage first. Open and Closed describe the period; Processing and Finalized describe its payroll runs.</p>
        <x-results :items="$periods" />
        <x-table label="Payroll Periods records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Period</th>
                    <th scope="col" class="px-4 py-3 text-left">Type</th>
                    <th scope="col" class="px-4 py-3 text-left">Coverage</th>
                    <th scope="col" class="px-4 py-3 text-left">Pay Date</th>
                    <th scope="col" class="px-4 py-3 text-left">Period status</th>
                    <th scope="col" class="px-4 py-3 text-right">Runs</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($periods as $period)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $period->period_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ ucfirst(strtolower($period->payroll_type)) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $period->start_date?->format('M d, Y') }} – {{ $period->end_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $period->pay_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3"><x-status :value="$period->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <span class="font-semibold tabular-nums">{{ $period->runs_count }}</span>
                            @if ($period->processing_runs_count)
                                <div class="mt-2"><x-status value="PROCESSING" /> <span class="text-xs">{{ $period->processing_runs_count }} runs</span></div>
                            @endif
                            @if ($period->finalized_runs_count)
                                <div class="mt-2"><x-status value="FINALIZED" /> <span class="text-xs">{{ $period->finalized_runs_count }} runs</span></div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @include('payroll-periods._actions')
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
