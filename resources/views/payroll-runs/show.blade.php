@extends('layouts.app')

@section('title', 'Payroll Run')
@section('heading', 'Payroll Run · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1 font-semibold">{{ $run->status }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Type</dt>
                <dd class="mt-1">{{ $run->run_type }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employees</dt>
                <dd class="mt-1">{{ $run->details->count() }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Processed</dt>
                <dd class="mt-1">{{ $run->processed_at?->format('M d, Y g:i A') ?? '—' }}</dd>
            </div>
        </dl>

        @can('payroll.prepare')
            @if ($run->isEditable())
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('payroll-runs.compute', $run) }}">
                        @csrf
                        <button class="rounded-md bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                            {{ $run->details->isEmpty() ? 'Compute payroll' : 'Recompute' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('payroll-runs.destroy', $run) }}" onsubmit="return confirm('Delete this draft run?')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-md border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Delete</button>
                    </form>
                </div>
            @endif
        @endcan
    </div>

    @if (! $run->details->isEmpty())
        <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Total Gross</div>
                <div class="mt-1 font-mono text-lg font-semibold">₱{{ number_format($totalGross, 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Total Deductions</div>
                <div class="mt-1 font-mono text-lg font-semibold">₱{{ number_format($totalDeductions, 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Total Net Pay</div>
                <div class="mt-1 font-mono text-lg font-semibold">₱{{ number_format($totalNet, 2) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs uppercase tracking-wide text-slate-500">Employer Contributions</div>
                <div class="mt-1 font-mono text-lg font-semibold">₱{{ number_format($employerContributions, 2) }}</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-3 text-left">Employee</th>
                        <th class="px-3 py-3 text-right">Basic Pay</th>
                        <th class="px-3 py-3 text-right">Earnings</th>
                        <th class="px-3 py-3 text-right">Gross</th>
                        <th class="px-3 py-3 text-right">Deductions</th>
                        <th class="px-3 py-3 text-right">NTHP</th>
                        <th class="px-3 py-3 text-right">LBP</th>
                        <th class="px-3 py-3 text-right">Net Pay</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($run->details as $detail)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-3">
                                <div class="font-medium text-slate-800">{{ $detail->employee_name_snapshot }}</div>
                                <div class="text-[10px] text-slate-400">{{ $detail->position_snapshot }}</div>
                            </td>
                            <td class="px-3 py-3 text-right font-mono">{{ number_format((float) $detail->basic_pay, 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ number_format((float) $detail->total_earnings, 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ number_format((float) $detail->gross_pay, 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ number_format((float) $detail->total_deductions, 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ number_format((float) $detail->nthp_before_lbp, 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono">{{ number_format((float) $detail->lbp_loan_deduction, 2) }}</td>
                            <td class="px-3 py-3 text-right font-mono font-semibold">{{ number_format((float) $detail->net_pay, 2) }}</td>
                            <td class="px-3 py-3 text-right">
                                @can('payroll.prepare')
                                    @if ($run->isEditable())
                                        <a href="{{ route('payroll-details.edit', [$run, $detail]) }}" class="text-emerald-700 hover:underline">Edit</a>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
            No payroll details yet. Click <strong>Compute payroll</strong> to generate them.
        </div>
    @endif
@endsection
