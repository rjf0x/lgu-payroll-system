@extends('layouts.app')
@section('title', 'Employees · LGU Payroll')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-3xl font-bold">Employees</h1><p class="mt-2 text-slate-600">Employee records for payroll preparation.</p></div>
        @can('create', \App\Models\Employee::class)
            <a href="{{ route('employees.create') }}" class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Create employee</a>
        @endcan
    </div>
    <form method="GET" action="{{ route('employees.index') }}" class="mt-6 flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <label for="q" class="block text-sm font-semibold">Search by employee number, name, position, or office</label>
            <input id="q" name="q" value="{{ $search }}" maxlength="100" class="mt-2 w-full rounded border border-slate-300 bg-white p-3">
        </div>
        <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Search</button>
        <a href="{{ route('employees.index') }}" class="px-3 py-3 text-teal-800 underline">Clear</a>
    </form>
    <p class="mt-5 text-sm text-slate-600">{{ $employees->total() }} {{ $employees->total() === 1 ? 'employee' : 'employees' }} found</p>
    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Employee payroll records</caption>
            <thead class="bg-slate-50"><tr>
                <th scope="col" class="p-4">Employee number</th><th scope="col" class="p-4">Name</th><th scope="col" class="p-4">Position / office</th><th scope="col" class="p-4">Classification</th><th scope="col" class="p-4">Status</th><th scope="col" class="p-4">Action</th>
            </tr></thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr class="border-t border-slate-200">
                        <td class="p-4">{{ $employee->employee_number }}</td><td class="p-4">{{ $employee->full_name }}</td>
                        <td class="p-4">{{ $employee->position }}<span class="mt-1 block text-slate-500">{{ $employee->office }}</span></td>
                        <td class="p-4">{{ $employee->personnel_classification->label() }}</td><td class="p-4">{{ $employee->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="p-4"><a href="{{ route('employees.show', $employee) }}" class="font-semibold text-teal-800 underline" aria-label="View {{ $employee->full_name }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-slate-600">No employees match your search.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $employees->links() }}</div>
@endsection
