@extends('layouts.app')

@section('title', 'Leave Application')
@section('heading', 'Leave Application')

@section('content')
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</dt>
                    <dd class="mt-1">{{ $application->employee->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Leave Type</dt>
                    <dd class="mt-1">{{ $application->leaveType->leave_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Period</dt>
                    <dd class="mt-1">{{ $application->start_date?->format('M d, Y') }} – {{ $application->end_date?->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Days</dt>
                    <dd class="mt-1">{{ number_format((float) $application->total_days, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1 font-semibold">{{ $application->approval_status }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewed By</dt>
                    <dd class="mt-1">{{ $application->approvedBy?->username ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reason</dt>
                    <dd class="mt-1 text-slate-700">{{ $application->reason ?: '—' }}</dd>
                </div>
            </dl>

            @if ($application->approval_status === 'PENDING')
                <div class="mt-6 flex gap-3">
                    <form method="POST" action="{{ route('leave-applications.approve', $application) }}">
                        @csrf
                        <button class="rounded-md bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('leave-applications.reject', $application) }}">
                        @csrf
                        <button class="rounded-md border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Reject</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4 text-sm font-semibold text-slate-700">Leave Transactions</div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($application->transactions as $transaction)
                    <li class="px-6 py-3">
                        <div class="flex items-center justify-between">
                            <span>{{ $transaction->transaction_type }} · {{ number_format((float) $transaction->days, 2) }} day(s)</span>
                            <span class="text-xs text-slate-500">{{ number_format((float) $transaction->balance_after, 2) }}</span>
                        </div>
                        <div class="text-xs text-slate-400">{{ $transaction->transaction_date?->format('M d, Y') }}</div>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-slate-500">No transactions.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
