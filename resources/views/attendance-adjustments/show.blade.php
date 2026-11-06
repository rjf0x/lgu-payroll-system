@extends('layouts.app')

@section('title', 'Adjustment')
@section('heading', 'Attendance Adjustment')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</dt>
                <dd class="mt-1">{{ $adjustment->attendanceRecord->employee->full_name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Date</dt>
                <dd class="mt-1">{{ $adjustment->attendanceRecord->attendance_date?->format('M d, Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1 font-semibold">{{ $adjustment->approval_status }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Old Value</dt>
                <dd class="mt-1">{{ $adjustment->old_attendance_status }} ({{ number_format((float) $adjustment->old_credited_days, 2) }})</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested Value</dt>
                <dd class="mt-1">{{ $adjustment->requested_attendance_status }} ({{ number_format((float) $adjustment->requested_credited_days, 2) }})</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested By</dt>
                <dd class="mt-1">{{ $adjustment->requestedBy?->username }} · {{ $adjustment->requested_at?->format('M d, Y g:i A') }}</dd>
            </div>
            <div class="sm:col-span-3">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reason</dt>
                <dd class="mt-1 text-slate-700">{{ $adjustment->adjustment_reason }}</dd>
            </div>
            @if ($adjustment->reviewedBy)
                <div class="sm:col-span-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reviewed By</dt>
                    <dd class="mt-1">{{ $adjustment->reviewedBy->username }} · {{ $adjustment->reviewed_at?->format('M d, Y g:i A') }}</dd>
                </div>
            @endif
        </dl>

        @if ($adjustment->approval_status === 'PENDING')
            <div class="mt-6 flex gap-3">
                <form method="POST" action="{{ route('attendance-adjustments.approve', $adjustment) }}">
                    @csrf
                    <button class="rounded-md bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Approve &amp; apply</button>
                </form>
                <form method="POST" action="{{ route('attendance-adjustments.reject', $adjustment) }}">
                    @csrf
                    <button class="rounded-md border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Reject</button>
                </form>
            </div>
        @endif
    </div>
@endsection
