@extends('layouts.app')

@section('title', 'Leave Applications')
@section('heading', 'Leave Applications')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-500">Status</label>
                <select name="status" onchange="this.form.submit()" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach (['PENDING', 'APPROVED', 'REJECTED'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(strtolower($option)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Employee</label>
                <select name="employee_id" onchange="this.form.submit()" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->employee_id }}" @selected($employeeId === $employee->employee_id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <a href="{{ route('leave-applications.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ File Leave</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Leave Type</th>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-right">Days</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($applications as $application)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $application->employee->full_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $application->leaveType->leave_name }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $application->start_date?->format('M d, Y') }} – {{ $application->end_date?->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $application->total_days, 2) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $application->approval_status === 'APPROVED' ? 'bg-emerald-100 text-emerald-700' : ($application->approval_status === 'REJECTED' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $application->approval_status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('leave-applications.show', $application) }}" class="text-emerald-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No leave applications.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $applications->links() }}</div>
@endsection
