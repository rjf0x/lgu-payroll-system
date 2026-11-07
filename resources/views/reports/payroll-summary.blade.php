@extends('layouts.app')

@section('title', 'Payroll Summary')
@section('heading', 'Payroll Summary · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Totals by department for this payroll run.</p>
        <div class="flex gap-2">
            <a href="{{ route('payroll-runs.register', $run) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Register</a>
            @can('reports.generate')
                <a href="{{ route('payroll-runs.register.export', $run) }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Export CSV</a>
            @endcan
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 text-left">Department</th>
                    <th class="px-5 py-3 text-right">Employees</th>
                    <th class="px-5 py-3 text-right">Gross</th>
                    <th class="px-5 py-3 text-right">Deductions</th>
                    <th class="px-5 py-3 text-right">Net</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($byDepartment as $department => $totals)
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3">{{ $department ?: '—' }}</td>
                        <td class="px-5 py-3 text-right">{{ $totals['count'] }}</td>
                        <td class="px-5 py-3 text-right font-mono">{{ number_format($totals['gross'], 2) }}</td>
                        <td class="px-5 py-3 text-right font-mono">{{ number_format($totals['deductions'], 2) }}</td>
                        <td class="px-5 py-3 text-right font-mono font-semibold">{{ number_format($totals['net'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No details for this run.</td></tr>
                @endforelse
            </tbody>
            @if ($byDepartment->isNotEmpty())
                <tfoot class="bg-slate-100 font-semibold">
                    <tr>
                        <td class="px-5 py-3">TOTAL</td>
                        <td class="px-5 py-3 text-right">{{ $byDepartment->sum('count') }}</td>
                        <td class="px-5 py-3 text-right font-mono">{{ number_format($byDepartment->sum('gross'), 2) }}</td>
                        <td class="px-5 py-3 text-right font-mono">{{ number_format($byDepartment->sum('deductions'), 2) }}</td>
                        <td class="px-5 py-3 text-right font-mono">{{ number_format($byDepartment->sum('net'), 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
