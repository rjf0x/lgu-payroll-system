@extends('layouts.app')

@section('title', 'Positions')
@section('heading', 'Positions')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Positions and the salary grade assigned to each.</p>
        @can('employees.manage')
            <x-button icon="add" variant="primary" href="{{ route('positions.create') }}">
                Add Position
            </x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$positions" />
        <x-table label="Positions records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Code</th>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Title</th>
                    <th scope="col" class="px-4 py-3 text-left">Department</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">SG</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($positions as $position)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $position->position_code }}</td>
                        <td class="px-4 py-3 ui-table-primary">{{ $position->position_title }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $position->department->department_name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $position->salary_grade ?? '—' }}</td>
                        <td class="px-4 py-3 text-center"><x-status :value="$position->status" /></td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for '.$position->position_title">
                                <x-button icon="view" variant="secondary" href="{{ route('positions.show', $position) }}">View</x-button>
                                @can('employees.manage')
                                    <x-button icon="edit" variant="secondary" href="{{ route('positions.edit', $position) }}">Edit</x-button>
                                @endcan
                            </x-action-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No positions yet.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $positions->links() }}</div>
@endsection
