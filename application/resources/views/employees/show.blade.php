@extends('layouts.app')
@section('title', 'Employee details · LGU Payroll')
@section('content')
    <a href="{{ route('employees.index') }}" class="text-teal-800 underline">Back to employees</a>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
        <div><p class="text-sm font-semibold text-teal-700">{{ $employee->employee_number }}</p><h1 class="mt-2 text-3xl font-bold">{{ $employee->full_name }}</h1></div>
        @can('update', $employee)
            <a href="{{ route('employees.edit', $employee) }}" class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Edit employee</a>
        @endcan
    </div>
    <a href="{{ route('employee-salaries.index', $employee) }}" class="mt-5 inline-block font-semibold text-teal-800 underline">Salary and preserved history</a>
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">Employment and payroll information</h2>
        <dl class="mt-5 grid gap-6 sm:grid-cols-2">
            <div><dt class="text-sm font-semibold text-slate-500">First name</dt><dd class="mt-1">{{ $employee->first_name }}</dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Middle name</dt><dd class="mt-1">{{ $employee->middle_name ?? '—' }}</dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Last name</dt><dd class="mt-1">{{ $employee->last_name }}</dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Suffix</dt><dd class="mt-1">{{ $employee->suffix ?? '—' }}</dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Position</dt><dd class="mt-1">{{ $employee->position }}</dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Office / department</dt><dd class="mt-1">{{ $employee->office }}</dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Personnel classification</dt><dd class="mt-1">{{ $employee->personnel_classification->label() }}</dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Employment start date</dt><dd class="mt-1">{{ $employee->employment_start_date->format('Y-m-d') }}</dd></div>
            <div><dt class="text-sm font-semibold text-slate-500">Record status</dt><dd class="mt-1">{{ $employee->is_active ? 'Active' : 'Inactive' }}</dd></div>
        </dl>
    </section>
@endsection
