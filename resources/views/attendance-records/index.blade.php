@extends('layouts.app')

@section('title', 'Attendance Records')
@section('heading', 'Attendance Records')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="filter-employee-id" class="mb-1.5 block text-sm font-semibold">Employee</label>
                <select name="employee_id" class="ui-field" id="filter-employee-id">
                    <option value="">All employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->employee_id }}" @selected($employeeId === $employee->employee_id)>
                            {{ $employee->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-month" class="mb-1.5 block text-sm font-semibold">Month</label>
                <input type="month" name="month" value="{{ $month }}" class="ui-field" id="filter-month">
            </div>
            <button class="ui-button ui-button-secondary">Filter</button>
            <x-button :href="route('attendance-records.index')">Reset</x-button>
        </form>

        <a href="{{ route('attendance-records.create') }}" class="ui-button ui-button-primary">
            + Record Attendance
        </a>
    </div>

    <div class="min-w-0">
        <x-results :items="$records" />
        <x-table label="Attendance Records records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Date</th>
                    <th scope="col" class="px-4 py-3 text-left">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Credited Days</th>
                    <th scope="col" class="px-4 py-3 text-left">Source</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
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
                            <a href="{{ route('attendance-adjustments.create', ['attendance_id' => $record->attendance_id]) }}" class="text-brand hover:underline">Adjust</a>
                            <a href="{{ route('attendance-records.edit', $record) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No attendance records.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $records->links() }}</div>
@endsection
