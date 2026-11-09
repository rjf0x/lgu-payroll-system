@extends('layouts.app')

@section('title', 'Departments')
@section('heading', 'Departments')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Organizational units used by positions and employment records.</p>
        @can('employees.manage')
            <x-button icon="add" variant="primary" href="{{ route('departments.create') }}">
                + Add Department
            </x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$departments" />
        <x-table label="Departments records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Code</th>
                    <th scope="col" class="px-4 py-3 text-left">Name</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Positions</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($departments as $department)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $department->department_code }}</td>
                        <td class="px-4 py-3">{{ $department->department_name }}</td>
                        <td class="px-4 py-3"><x-status :value="$department->status" /></td>
                        <td class="px-4 py-3 text-right">{{ $department->positions_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <x-button icon="view" variant="secondary" href="{{ route('departments.show', $department) }}">View</x-button>
                            @can('employees.manage')
                                <x-button icon="edit" variant="secondary" href="{{ route('departments.edit', $department) }}">Edit</x-button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No departments yet.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $departments->links() }}</div>
@endsection
