@extends('layouts.app')

@section('title', 'Monthly Payroll')
@section('heading', 'Monthly Payroll Runs')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Payroll runs per period, with computation and approval status.</p>
        @can('payroll.prepare')
            <a href="{{ route('payroll-runs.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ New Payroll Run</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-center">Run</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Employees</th>
                    <th class="px-4 py-3 text-left">Created By</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($runs as $run)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $run->payrollPeriod->period_name }}</td>
                        <td class="px-4 py-3 text-center">#{{ $run->run_number }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $run->run_type }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $run->status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ $run->details_count }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $run->createdBy?->username }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('payroll-runs.show', $run) }}" class="text-emerald-700 hover:underline">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No payroll runs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $runs->links() }}</div>
@endsection
