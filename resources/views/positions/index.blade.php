@extends('layouts.app')

@section('title', 'Positions')
@section('heading', 'Positions')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Positions and the salary grade assigned to each.</p>
        @can('employees.manage')
            <a href="{{ route('positions.create') }}" class="ui-button ui-button-primary">
                + Add Position
            </a>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$positions" />
        <x-table label="Positions records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Code</th>
                    <th scope="col" class="px-4 py-3 text-left">Title</th>
                    <th scope="col" class="px-4 py-3 text-left">Department</th>
                    <th scope="col" class="px-4 py-3 text-center">SG</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($positions as $position)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $position->position_code }}</td>
                        <td class="px-4 py-3">{{ $position->position_title }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $position->department->department_name }}</td>
                        <td class="px-4 py-3 text-center">{{ $position->salary_grade ?? '—' }}</td>
                        <td class="px-4 py-3"><x-status :value="$position->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('positions.show', $position) }}" class="text-brand hover:underline">View</a>
                            @can('employees.manage')
                                <a href="{{ route('positions.edit', $position) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                            @endcan
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
