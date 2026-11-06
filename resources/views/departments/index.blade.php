@extends('layouts.app')

@section('title', 'Departments')
@section('heading', 'Departments')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Organizational units used by positions and employment records.</p>
        @can('employees.manage')
            <a href="{{ route('departments.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                + Add Department
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Code</th>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Positions</th>
                    <th class="px-4 py-3 text-right">Actions</th>
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
                            <a href="{{ route('departments.show', $department) }}" class="text-emerald-700 hover:underline">View</a>
                            @can('employees.manage')
                                <a href="{{ route('departments.edit', $department) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No departments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $departments->links() }}</div>
@endsection
