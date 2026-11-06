@extends('layouts.app')

@section('title', 'Employees')
@section('heading', 'Employees')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Search name or employee number"
                class="w-72 rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:outline-none"
            >
            <button class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Search</button>
        </form>

        @can('employees.manage')
            <a href="{{ route('employees.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                + Add Employee
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee No.</th>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Sex</th>
                    <th class="px-4 py-3 text-right">Employments</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $employee)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $employee->employee_number }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('employees.show', $employee) }}" class="font-medium text-emerald-700 hover:underline">{{ $employee->full_name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $employee->sex }}</td>
                        <td class="px-4 py-3 text-right">{{ $employee->employment_records_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('employees.show', $employee) }}" class="text-emerald-700 hover:underline">View</a>
                            @can('employees.manage')
                                <a href="{{ route('employees.edit', $employee) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No employees found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $employees->links() }}</div>
@endsection
