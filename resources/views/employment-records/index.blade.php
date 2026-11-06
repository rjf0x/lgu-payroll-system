@extends('layouts.app')

@section('title', 'Employment Records')
@section('heading', 'Employment Records')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Appointments linking employees to departments, positions, and employment types.</p>
        @can('employees.manage')
            <a href="{{ route('employment-records.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                + Add Employment Record
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Position</th>
                    <th class="px-4 py-3 text-left">Department</th>
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Start</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employmentRecords as $record)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('employment-records.show', $record) }}" class="font-medium text-emerald-700 hover:underline">
                                {{ $record->employee->full_name }}
                            </a>
                            <div class="font-mono text-[10px] text-slate-400">{{ $record->employee->employee_number }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $record->position->position_title }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->department->department_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->employmentType->employment_type_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->start_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3"><x-status :value="$record->employment_status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('employment-records.show', $record) }}" class="text-emerald-700 hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No employment records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $employmentRecords->links() }}</div>
@endsection
