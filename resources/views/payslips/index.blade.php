@extends('layouts.app')

@section('title', 'Payslips')
@section('heading', 'Payslips · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Payslips can be generated once the run is approved or finalized.</p>
        <div class="flex flex-wrap gap-3">
            <x-button icon="back" variant="secondary" href="{{ route('payroll-runs.show', $run) }}">Back to run</x-button>
            @can('reports.generate')
                <form method="POST" action="{{ route('payroll-runs.payslips.generate', $run) }}" data-submit-feedback>
                    @csrf
                    <x-button icon="document" type="submit" :disabled="! $canGenerate" variant="primary">
                        Generate payslips
                    </x-button>
                </form>
            @endcan
        </div>
    </div>

    <div class="min-w-0">
        <x-table label="Payslips records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Employee</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Net Pay</th>
                    <th scope="col" class="px-4 py-3 text-left">Payslip No.</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($run->details as $detail)
                    <tr>
                        <td class="px-4 py-3 ui-table-primary">{{ $detail->employee_name_snapshot }}</td>
                        <td class="px-4 py-3 font-mono text-right tabular-nums">{{ number_format((float) $detail->net_pay, 2) }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $detail->payslip?->payslip_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($detail->payslip)
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $detail->payslip->publication_status === 'PUBLISHED' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $detail->payslip->publication_status }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400">Not generated</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for payslip for '.$detail->employee_name_snapshot">
                                @if ($detail->payslip)
                                    <x-button icon="view" variant="secondary" href="{{ route('payslips.show', $detail->payslip) }}">View</x-button>
                                    @can('reports.generate')
                                        @if ($detail->payslip->publication_status !== 'PUBLISHED')
                                            <form method="POST" action="{{ route('payslips.publish', $detail->payslip) }}" data-submit-feedback>
                                                @csrf
                                                <x-button icon="send" variant="secondary" type="submit">Publish</x-button>
                                            </form>
                                        @endif
                                    @endcan
                                @endif
                            </x-action-menu>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    </div>
@endsection
