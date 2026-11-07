@extends('layouts.app')

@section('title', 'Payroll Register')
@section('heading', 'Payroll Register · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ $run->details->count() }} employee(s) · monthly payroll register.</p>
        <div class="flex gap-2">
            <a href="{{ route('payroll-runs.show', $run) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Back to run</a>
            <a href="{{ route('payroll-runs.payslips', $run) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Payslips</a>
            @can('reports.generate')
                <a href="{{ route('payroll-runs.register.export', $run) }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Export CSV</a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-xs">
            <thead class="bg-slate-50 uppercase tracking-wide text-slate-500">
                <tr>
                    @foreach ($headers as $header)
                        <th class="whitespace-nowrap px-3 py-3 text-left">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr class="hover:bg-slate-50">
                        @foreach ($row as $cell)
                            <td class="whitespace-nowrap px-3 py-2 {{ is_float($cell) ? 'text-right font-mono' : '' }}">
                                {{ is_float($cell) ? number_format($cell, 2) : $cell }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($headers) }}" class="px-3 py-8 text-center text-slate-500">No payroll details.</td></tr>
                @endforelse
            </tbody>
            @if (! empty($rows))
                <tfoot class="bg-slate-100 font-semibold">
                    <tr>
                        @foreach ($totals as $cell)
                            <td class="whitespace-nowrap px-3 py-3 {{ is_float($cell) ? 'text-right font-mono' : '' }}">
                                {{ is_float($cell) ? number_format($cell, 2) : $cell }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
