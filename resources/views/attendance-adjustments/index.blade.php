@extends('layouts.app')

@section('title', 'Attendance Adjustments')
@section('heading', 'Attendance Adjustments')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <select name="status" onchange="this.form.submit()" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach (['PENDING', 'APPROVED', 'REJECTED'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(strtolower($option)) }}</option>
                @endforeach
            </select>
        </form>

        <a href="{{ route('attendance-adjustments.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
            + Request Adjustment
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Change</th>
                    <th class="px-4 py-3 text-left">Requested By</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($adjustments as $adjustment)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $adjustment->attendanceRecord->attendance_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3">{{ $adjustment->attendanceRecord->employee->full_name }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $adjustment->old_attendance_status }} ({{ number_format((float) $adjustment->old_credited_days, 2) }})
                            → {{ $adjustment->requested_attendance_status }} ({{ number_format((float) $adjustment->requested_credited_days, 2) }})
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $adjustment->requestedBy?->username }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $adjustment->approval_status === 'APPROVED' ? 'bg-emerald-100 text-emerald-700' : ($adjustment->approval_status === 'REJECTED' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $adjustment->approval_status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('attendance-adjustments.show', $adjustment) }}" class="text-emerald-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No adjustments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $adjustments->links() }}</div>
@endsection
