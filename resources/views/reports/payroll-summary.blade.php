@extends('layouts.app')

@section('title', 'Payroll Summary')
@section('heading', 'Payroll Summary · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <x-report-scope :run="$run" :organization-name="$systemSettings['organization_name']" />
    <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Totals by department for this payroll run.</p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('payroll-runs.register', $run) }}" class="ui-button ui-button-secondary">Register</a>
            @can('reports.generate')
                <a href="{{ route('payroll-runs.register.export', $run) }}" class="ui-button ui-button-primary">Export spreadsheet</a>
            @endcan
            <x-button onclick="window.print()">Print summary</x-button>
        </div>
    </div>

    <div class="min-w-0">
        <x-table label="Payroll Summary records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 text-left">Department</th>
                    <th scope="col" class="px-5 py-3 text-right">Employees</th>
                    <th scope="col" class="px-5 py-3 text-right">Gross</th>
                    <th scope="col" class="px-5 py-3 text-right">Deductions</th>
                    <th scope="col" class="px-5 py-3 text-right">Net</th>
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
        </x-table>
    </div>
@endsection
