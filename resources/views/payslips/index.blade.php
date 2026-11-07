@extends('layouts.app')

@section('title', 'Payslips')
@section('heading', 'Payslips · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Payslips can be generated once the run is approved or finalized.</p>
        <div class="flex gap-2">
            <a href="{{ route('payroll-runs.show', $run) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Back to run</a>
            @can('reports.generate')
                <form method="POST" action="{{ route('payroll-runs.payslips.generate', $run) }}">
                    @csrf
                    <button @disabled(! $canGenerate) class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">
                        Generate payslips
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-right">Net Pay</th>
                    <th class="px-4 py-3 text-left">Payslip No.</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($run->details as $detail)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $detail->employee_name_snapshot }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ number_format((float) $detail->net_pay, 2) }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $detail->payslip?->payslip_number ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($detail->payslip)
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $detail->payslip->publication_status === 'PUBLISHED' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $detail->payslip->publication_status }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400">Not generated</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($detail->payslip)
                                <a href="{{ route('payslips.show', $detail->payslip) }}" class="text-emerald-700 hover:underline">View</a>
                                @can('reports.generate')
                                    @if ($detail->payslip->publication_status !== 'PUBLISHED')
                                        <form method="POST" action="{{ route('payslips.publish', $detail->payslip) }}" class="ml-3 inline">
                                            @csrf
                                            <button class="text-slate-600 hover:underline">Publish</button>
                                        </form>
                                    @endif
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
