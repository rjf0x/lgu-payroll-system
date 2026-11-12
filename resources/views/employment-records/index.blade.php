@extends('layouts.app')

@section('title', 'Employment Records')
@section('heading', 'Employment Records')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Appointments linking employees to departments, positions, and employment types.</p>
        @can('employees.manage')
            <x-button icon="add" variant="primary" href="{{ route('employment-records.create') }}">
                Add Employment Record
            </x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$employmentRecords" />
        <x-table label="Employment Records records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left">Position</th>
                    <th scope="col" class="px-4 py-3 text-left">Department</th>
                    <th scope="col" class="px-4 py-3 text-left">Type</th>
                    <th scope="col" class="px-4 py-3 text-left">Start</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employmentRecords as $record)
                    <tr>
                        <td class="px-4 py-3 ui-table-primary">
                            <a href="{{ route('employment-records.show', $record) }}" class="font-medium text-brand hover:underline">
                                {{ $record->employee->full_name }}
                            </a>
                            <div class="ui-table-secondary font-mono">{{ $record->employee->employee_number }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $record->position->position_title }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->department->department_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->employmentType->employment_type_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $record->start_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-center"><x-status :value="$record->employment_status" /></td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for employment record #'.$record->getKey()">
                                <x-button icon="view" variant="secondary" href="{{ route('employment-records.show', $record) }}">View</x-button>
                            </x-action-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No employment records.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $employmentRecords->links() }}</div>
@endsection
