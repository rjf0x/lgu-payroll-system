@extends('layouts.app')
@section('content')
    <h1 class="text-3xl font-bold">{{ $role->label() }} workspace</h1>
    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        @if ($role === \App\Enums\Role::Administrator)
            <h2 class="text-xl font-semibold">User access</h2>
            <p class="mt-3 text-slate-600">Create accounts, assign roles, reset passwords, and deactivate access.</p>
            <a href="{{ route('users.index') }}" class="mt-4 inline-block font-semibold text-teal-800 underline">Manage user accounts</a>
        @else
            <h2 class="text-xl font-semibold">{{ match ($role) {
                \App\Enums\Role::PayrollOfficer => 'Payroll preparation',
                \App\Enums\Role::Approver => 'Payroll review',
                \App\Enums\Role::Viewer => 'Payroll records',
            } }}</h2>
            <p class="mt-3 text-slate-600">Your access is ready. Payroll features will be introduced in later development phases.</p>
            @can('viewAny', \App\Models\Employee::class)
                <a href="{{ route('employees.index') }}" class="mt-4 inline-block font-semibold text-teal-800 underline">{{ $role === \App\Enums\Role::PayrollOfficer ? 'Manage employees' : 'View employees' }}</a>
            @endcan
        @endif
    </section>
@endsection
