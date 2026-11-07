@extends('layouts.app')
@section('title', ($dashboardRole ?? 'Account').' Dashboard')
@section('heading', ($dashboardRole ?? 'Account').' Dashboard')
@section('content')
<div class="flex flex-col gap-7">
    <section class="rounded-2xl bg-emerald-900 p-6 text-white sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-200">LGU Payroll Management</p>
        <h2 class="mt-3 text-2xl font-semibold">Welcome, {{ auth()->user()->username }}</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-emerald-100">
            @switch($dashboardRole)
                @case('Admin') Manage system access, configuration, and records oversight. @break
                @case('Payroll Officer') Maintain payroll inputs, prepare computations, and submit payroll for independent approval. @break
                @case('Approver') Review submitted payroll and make independent approval decisions. @break
                @case('Viewer') Browse finalized payroll records, employee information, and authorized reports. @break
                @default No role is assigned. Contact your administrator to request access.
            @endswitch
        </p>
    </section>
    @if ($metrics)
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($metrics as $label => $value)
                <x-dashboard-stat :label="$label" :value="$value" />
            @endforeach
        </dl>
    @endif
    <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="font-semibold">Quick actions</h2>
        <div class="mt-4 flex flex-wrap gap-3">
            @can('users.manage') <x-dashboard-action :href="route('users.create')">Add user</x-dashboard-action> @endcan
            @can('employees.view') <x-dashboard-action :href="route('employees.index')">Employee directory</x-dashboard-action> @endcan
            @can('compensation.manage') <x-dashboard-action :href="route('salary-schedules.index')">Salary schedules</x-dashboard-action> @endcan
            @can('attendance.manage') <x-dashboard-action :href="route('attendance-records.index')">Record attendance</x-dashboard-action> @endcan
            @can('payroll.prepare') <x-dashboard-action :href="route('payroll-runs.create')">Prepare payroll</x-dashboard-action> @endcan
            @can('payroll.approve') <x-dashboard-action :href="route('payroll-runs.index', ['status' => 'PENDING_APPROVAL'])">Pending approvals</x-dashboard-action> @endcan
            @can('payroll.view') <x-dashboard-action :href="route('reports.index')">View reports</x-dashboard-action> @endcan
            @can('blockchain.verify') <x-dashboard-action :href="route('blockchain.index')">Blockchain verification</x-dashboard-action> @endcan
        </div>
    </section>
    @if ($roles->isNotEmpty())
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="font-semibold">System user overview</h2>
            <p class="mt-1 text-sm text-slate-500">Accounts by assigned role. Accounts with multiple roles appear in each role.</p>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($roles as $role)
                    <div><dt class="text-sm text-slate-500">{{ $role->role_name }}</dt><dd class="mt-1 text-2xl font-semibold">{{ $role->users_count }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif
    @can('payroll.view')
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 p-6">
                <h2 class="font-semibold">{{ $dashboardRole === 'Approver' ? 'Payroll awaiting your review' : ($dashboardRole === 'Viewer' ? 'Finalized payroll records' : 'Recent payroll runs') }}</h2>
                <a href="{{ route('payroll-runs.index') }}" class="text-sm font-semibold text-emerald-700 hover:underline">View all payroll</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-y border-slate-200 bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-6 py-3">Payroll period</th><th class="px-6 py-3">Run</th><th class="px-6 py-3">Employees</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Details</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($runs as $run)
                            <tr><td class="px-6 py-4 font-medium">{{ $run->payrollPeriod->period_name }}</td><td class="px-6 py-4">#{{ $run->run_number }}</td><td class="px-6 py-4">{{ $run->details_count }}</td><td class="px-6 py-4"><x-status :value="$run->status" /></td><td class="px-6 py-4"><a class="font-semibold text-emerald-700 hover:underline" href="{{ route('payroll-runs.show', $run) }}">View run <span class="sr-only">{{ $run->payroll_run_id }}</span></a></td></tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-slate-500">No payroll runs to display.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endcan
</div>
@endsection
