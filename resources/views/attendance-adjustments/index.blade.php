@extends('layouts.app')

@section('title', 'Attendance Adjustments')
@section('heading', 'Attendance Adjustments')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex min-w-0 flex-wrap items-end gap-3">
            <select name="status" class="ui-field" aria-label="Status" id="filter-status">
                <option value="">All statuses</option>
                @foreach (['PENDING', 'APPROVED', 'REJECTED'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst(strtolower($option)) }}</option>
                @endforeach
            </select>
            <x-button type="submit">Apply filters</x-button>
            <x-button :href="route('attendance-adjustments.index')">Reset</x-button>
        </form>

        <a href="{{ route('attendance-adjustments.create') }}" class="ui-button ui-button-primary">
            + Request Adjustment
        </a>
    </div>

    <div class="min-w-0">
        <x-results :items="$adjustments" />
        <x-table label="Attendance Adjustments records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Date</th>
                    <th scope="col" class="px-4 py-3 text-left">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left">Change</th>
                    <th scope="col" class="px-4 py-3 text-left">Requested By</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3">Actions</th>
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
                            <x-status :value="$adjustment->approval_status" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('attendance-adjustments.show', $adjustment) }}" class="text-emerald-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No adjustments.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $adjustments->links() }}</div>
@endsection
