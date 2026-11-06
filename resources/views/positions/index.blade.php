@extends('layouts.app')

@section('title', 'Positions')
@section('heading', 'Positions')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Positions and the salary grade assigned to each.</p>
        @can('employees.manage')
            <a href="{{ route('positions.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                + Add Position
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Code</th>
                    <th class="px-4 py-3 text-left">Title</th>
                    <th class="px-4 py-3 text-left">Department</th>
                    <th class="px-4 py-3 text-center">SG</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
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
                            <a href="{{ route('positions.show', $position) }}" class="text-emerald-700 hover:underline">View</a>
                            @can('employees.manage')
                                <a href="{{ route('positions.edit', $position) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No positions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $positions->links() }}</div>
@endsection
