@extends('layouts.app')

@section('title', 'Payroll Periods')
@section('heading', 'Payroll Periods')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Monthly payroll periods and their pay dates.</p>
        @can('payroll.prepare')
            <a href="{{ route('payroll-periods.create') }}" class="ui-button ui-button-primary">+ Add Period</a>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$periods" />
        <x-table label="Payroll Periods records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Period</th>
                    <th scope="col" class="px-4 py-3 text-left">Type</th>
                    <th scope="col" class="px-4 py-3 text-left">Coverage</th>
                    <th scope="col" class="px-4 py-3 text-left">Pay Date</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Runs</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($periods as $period)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $period->period_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $period->payroll_type }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $period->start_date?->format('M d, Y') }} – {{ $period->end_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $period->pay_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3"><x-status :value="$period->status" /></td>
                        <td class="px-4 py-3 text-right">{{ $period->runs_count }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('payroll.prepare')
                                <a href="{{ route('payroll-periods.edit', $period) }}" class="text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No payroll periods.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $periods->links() }}</div>
@endsection
