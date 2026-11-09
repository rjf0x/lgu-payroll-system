@extends('layouts.app')

@section('title', 'Payroll Register')
@section('heading', 'Payroll Register · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <x-report-scope :run="$run" :organization-name="$systemSettings['organization_name']" />
    <div class="no-print mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">{{ $run->details->count() }} employee(s) · monthly payroll register.</p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('payroll-runs.show', $run) }}" class="ui-button ui-button-secondary">Back to run</a>
            <a href="{{ route('payroll-runs.payslips', $run) }}" class="ui-button ui-button-secondary">Payslips</a>
            @can('reports.generate')
                <a href="{{ route('payroll-runs.register.export', $run) }}" class="ui-button ui-button-primary">Export spreadsheet</a>
            @endcan
            <x-button onclick="window.print()">Print register</x-button>
        </div>
    </div>

    <div class="min-w-0">
        <x-table label="Payroll Register records">
            <thead class="bg-slate-50 uppercase tracking-wide text-slate-500">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="whitespace-nowrap px-3 py-3 text-left">{{ $header }}</th>
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
        </x-table>
    </div>
@endsection

@push('print-styles')
    <style>
        @media print {
            @page { size: A3 landscape; }
            .ui-table { font-size: 6.5pt; }
            .ui-table th, .ui-table td { padding: 1.5mm 0.7mm; }
            .ui-table td.text-right { white-space: nowrap !important; overflow-wrap: normal; }
            .ui-table td:nth-child(2) { white-space: nowrap !important; }
            .ui-table th:first-child, .ui-table th:nth-child(3), .ui-table th:nth-child(4) { min-width: 20mm; }
        }
    </style>
@endpush
