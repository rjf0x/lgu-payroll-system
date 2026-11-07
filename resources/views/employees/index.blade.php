@extends('layouts.app')

@section('title', 'Employees')
@section('heading', 'Employees')

@section('content')
    <x-page-header description="Find an employee by name or employee number, then view their employment records.">
        @can('employees.manage')
            <x-button :href="route('employees.create')" variant="primary">Add Employee</x-button>
        @endcan
    </x-page-header>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('employees.index') }}" role="search" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <x-form.input name="q" label="Search employees" type="search" :value="$search" placeholder="Name or employee number" />
            </div>
            <div class="flex flex-wrap gap-3">
                <x-button type="submit" variant="primary">Search</x-button>
                <x-button :href="route('employees.index')">Reset</x-button>
            </div>
        </form>
    </x-card>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-sm text-muted">
        <p>{{ number_format($employees->total()) }} {{ $employees->total() === 1 ? 'employee' : 'employees' }}{{ $search !== '' ? ' matching your search' : '' }}</p>
        <p>Scroll within the table to see all columns.</p>
    </div>
        <x-table label="Employee directory">
            <thead>
                <tr>
                    <th scope="col">Employee No.</th>
                    <th scope="col">Name</th>
                    <th scope="col">Sex</th>
                    <th scope="col" class="ui-money">Employments</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($employees as $employee)
                    <tr class="hover:bg-slate-50">
                        <td class="font-mono">{{ $employee->employee_number }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('employees.show', ['employee' => $employee] + request()->only(['q', 'page'])) }}" class="font-medium text-brand hover:underline">{{ $employee->full_name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $employee->sex }}</td>
                        <td class="px-4 py-3 text-right">{{ $employee->employment_records_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('employees.show', ['employee' => $employee] + request()->only(['q', 'page'])) }}">View<span class="sr-only"> {{ $employee->full_name }}</span></a>
                            @can('employees.manage')
                                <a href="{{ route('employees.edit', $employee) }}" class="ml-4">Edit<span class="sr-only"> {{ $employee->full_name }}</span></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ui-wrap py-10 text-center text-muted">{{ $search !== '' ? 'No employees match your search. Try another name or reset the search.' : 'No employees found.' }}</td></tr>
                @endforelse
            </tbody>
    </x-table>

    <div class="mt-4">{{ $employees->links() }}</div>
@endsection
