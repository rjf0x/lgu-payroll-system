@extends('layouts.app')

@section('title', 'Payroll Summary')
@section('heading', 'Payroll Summary · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <x-report-scope :run="$run" :organization-name="$systemSettings['organization_name']" />
    <div class="no-print mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Totals by department for this payroll run.</p>
        <div class="flex flex-wrap gap-3">
            <x-button icon="document" variant="secondary" href="{{ route('payroll-runs.register', $run) }}">Register</x-button>
            @can('reports.generate')
                <x-button icon="download" variant="primary" href="{{ route('payroll-runs.register.export', $run) }}">Export spreadsheet</x-button>
            @endcan
            <x-button icon="print" onclick="window.print()">Print summary</x-button>
        </div>
    </div>

    <div class="min-w-0">
        <x-table label="Payroll Summary records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 text-left ui-table-primary">Department</th>
                    <th scope="col" class="px-5 py-3 text-right tabular-nums">Employees</th>
                    <th scope="col" class="px-5 py-3 text-right tabular-nums">Gross</th>
                    <th scope="col" class="px-5 py-3 text-right tabular-nums">Deductions</th>
                    <th scope="col" class="px-5 py-3 text-right tabular-nums">Net</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byDepartment as $department => $totals)
                    <tr>
                        <td class="px-5 py-3 ui-table-primary">{{ $department ?: '—' }}</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ $totals['count'] }}</td>
                        <td class="px-5 py-3 font-mono text-right tabular-nums">{{ number_format($totals['gross'], 2) }}</td>
                        <td class="px-5 py-3 font-mono text-right tabular-nums">{{ number_format($totals['deductions'], 2) }}</td>
                        <td class="px-5 py-3 font-mono font-semibold text-right tabular-nums">{{ number_format($totals['net'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No details for this run.</td></tr>
                @endforelse
            </tbody>
            @if ($byDepartment->isNotEmpty())
                <tfoot class="bg-slate-100 font-semibold">
                    <tr>
                        <td class="px-5 py-3 ui-table-primary">TOTAL</td>
                        <td class="px-5 py-3 text-right tabular-nums">{{ $byDepartment->sum('count') }}</td>
                        <td class="px-5 py-3 font-mono text-right tabular-nums">{{ number_format($byDepartment->sum('gross'), 2) }}</td>
                        <td class="px-5 py-3 font-mono text-right tabular-nums">{{ number_format($byDepartment->sum('deductions'), 2) }}</td>
                        <td class="px-5 py-3 font-mono text-right tabular-nums">{{ number_format($byDepartment->sum('net'), 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </x-table>
    </div>
@endsection
