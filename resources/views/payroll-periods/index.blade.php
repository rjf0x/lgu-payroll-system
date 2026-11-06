@extends('layouts.app')

@section('title', 'Payroll Periods')
@section('heading', 'Payroll Periods')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Monthly payroll periods and their pay dates.</p>
        @can('payroll.prepare')
            <a href="{{ route('payroll-periods.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ Add Period</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Coverage</th>
                    <th class="px-4 py-3 text-left">Pay Date</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Runs</th>
                    <th class="px-4 py-3 text-right">Actions</th>
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
        </table>
    </div>

    <div class="mt-4">{{ $periods->links() }}</div>
@endsection
