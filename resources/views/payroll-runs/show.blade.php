@extends('layouts.app')

@section('title', 'Payroll Run')
@section('heading', 'Payroll Run · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-3">
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
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Submitted By</dt>
                <dd class="mt-1">{{ $run->submittedBy?->username ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Finalized By</dt>
                <dd class="mt-1">{{ $run->finalizedBy?->username ?? '—' }}</dd>
            </div>
        </dl>

        <div class="flex flex-wrap items-center gap-2">
            @can('payroll.prepare')
                @if ($run->isEditable())
                    <form method="POST" action="{{ route('payroll-runs.compute', $run) }}">
                        @csrf
                        <button class="rounded-md bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                            {{ $run->details->isEmpty() ? 'Compute payroll' : 'Recompute' }}
                        </button>
                    </form>
                    @if ($canSubmit)
                        <form method="POST" action="{{ route('payroll-runs.submit', $run) }}">
                            @csrf
                            <button class="rounded-md border border-emerald-300 px-4 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Submit for approval</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('payroll-runs.destroy', $run) }}" onsubmit="return confirm('Delete this draft run?')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-md border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Delete</button>
                    </form>
                @endif
            @endcan

            @if ($run->details->isNotEmpty())
                <a href="{{ route('payroll-runs.register', $run) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Register</a>
                <a href="{{ route('payroll-runs.payslips', $run) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Payslips</a>
                <a href="{{ route('reports.payroll-summary', $run) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Summary</a>
            @endif

            @if ($canReview)
                <form method="POST" action="{{ route('payroll-runs.approve', $run) }}" class="flex items-center gap-2">
                    @csrf
                    <input type="text" name="remarks" placeholder="Remarks (optional)" class="rounded-md border border-slate-300 px-2 py-1 text-xs">
                    <button class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Approve</button>
                </form>
                <form method="POST" action="{{ route('payroll-runs.return', $run) }}">
                    @csrf
                    <button class="rounded-md border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50">Return</button>
                </form>
                <form method="POST" action="{{ route('payroll-runs.reject', $run) }}">
                    @csrf
                    <button class="rounded-md border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Reject</button>
                </form>
            @endif

            @if ($canFinalize)
                <form method="POST" action="{{ route('payroll-runs.finalize', $run) }}">
                    @csrf
                    <button class="rounded-md bg-slate-800 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-900">Finalize</button>
                </form>
            @endif
        </div>
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

    <div class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">Approval History</div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-2 text-left">Action</th>
                    <th class="px-5 py-2 text-left">Acted By</th>
                    <th class="px-5 py-2 text-left">When</th>
                    <th class="px-5 py-2 text-left">Remarks</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($run->approvals as $approval)
                    <tr>
                        <td class="px-5 py-2 font-semibold text-slate-700">{{ $approval->action }}</td>
                        <td class="px-5 py-2 text-slate-600">{{ $approval->actedBy?->username }}</td>
                        <td class="px-5 py-2 text-slate-500">{{ $approval->acted_at?->format('M d, Y g:i A') }}</td>
                        <td class="px-5 py-2 text-slate-500">{{ $approval->remarks ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-6 text-center text-slate-500">No approval actions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
