@extends('layouts.app')

@section('title', 'Leave Applications')
@section('heading', 'Leave Applications')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="filter-status" class="mb-1.5 block text-sm font-semibold">Status</label>
                <select name="status" class="ui-field" id="filter-status">
                    <option value="">All</option>
                    @foreach (['PENDING', 'APPROVED', 'REJECTED'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(strtolower($option)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-employee-id" class="mb-1.5 block text-sm font-semibold">Employee</label>
                <select name="employee_id" class="ui-field" id="filter-employee-id">
                    <option value="">All</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->employee_id }}" @selected($employeeId === $employee->employee_id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit">Apply filters</x-button>
            <x-button :href="route('leave-applications.index')">Reset</x-button>
        </form>

        <a href="{{ route('leave-applications.create') }}" class="ui-button ui-button-primary">+ File Leave</a>
    </div>

    <div class="min-w-0">
        <x-results :items="$applications" />
        <x-table label="Leave Applications records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left">Leave Type</th>
                    <th scope="col" class="px-4 py-3 text-left">Period</th>
                    <th scope="col" class="px-4 py-3 text-right">Days</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3">Actions</th>
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
                            <x-status :value="$application->approval_status" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('leave-applications.show', $application) }}" class="text-emerald-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No leave applications.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $applications->links() }}</div>
@endsection
