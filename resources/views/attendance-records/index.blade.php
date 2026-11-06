@extends('layouts.app')

@section('title', 'Attendance Records')
@section('heading', 'Attendance Records')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-500">Employee</label>
                <select name="employee_id" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->employee_id }}" @selected($employeeId === $employee->employee_id)>
                            {{ $employee->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Month</label>
                <input type="month" name="month" value="{{ $month }}" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Filter</button>
        </form>

        <a href="{{ route('attendance-records.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
            + Record Attendance
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Credited Days</th>
                    <th class="px-4 py-3 text-left">Source</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($records as $record)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $record->attendance_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3">{{ $record->employee->full_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->attendance_status }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $record->credited_days, 2) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $record->source ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('attendance-adjustments.create', ['attendance_id' => $record->attendance_id]) }}" class="text-emerald-700 hover:underline">Adjust</a>
                            <a href="{{ route('attendance-records.edit', $record) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No attendance records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $records->links() }}</div>
@endsection
