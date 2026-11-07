<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') | LGU Payroll System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">
<div class="flex min-h-full">

    <aside class="hidden w-60 shrink-0 flex-col bg-emerald-900 text-emerald-50 md:flex">
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-white text-sm font-bold text-emerald-800">LGU</span>
            <div>
                <div class="text-sm font-semibold">Payroll System</div>
                <div class="text-[10px] text-emerald-200">Local Government Unit</div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 px-3 py-4 text-sm">
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>

            @can('employees.view')
                <div class="px-3 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Organization</div>
                <x-nav-link :href="route('departments.index')" :active="request()->routeIs('departments.*')">Departments</x-nav-link>
                <x-nav-link :href="route('positions.index')" :active="request()->routeIs('positions.*')">Positions</x-nav-link>
                <x-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">Employees</x-nav-link>
                <x-nav-link :href="route('employment-records.index')" :active="request()->routeIs('employment-records.*')">Employment Records</x-nav-link>
            @endcan

            @can('employees.view')
                <div class="px-3 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Compensation</div>
                <x-nav-link :href="route('salary-schedules.index')" :active="request()->routeIs('salary-schedules.*')">Salary Schedules</x-nav-link>
                <x-nav-link :href="route('salary-grade-steps.index')" :active="request()->routeIs('salary-grade-steps.*')">Salary Grade Steps</x-nav-link>
                <x-nav-link :href="route('compensation-profiles.index')" :active="request()->routeIs('compensation-profiles.*')">Compensation Profiles</x-nav-link>

                <div class="px-3 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Earnings &amp; Deductions</div>
                <x-nav-link :href="route('earning-types.index')" :active="request()->routeIs('earning-types.*')">Earning Types</x-nav-link>
                <x-nav-link :href="route('employee-earning-assignments.index')" :active="request()->routeIs('employee-earning-assignments.*')">Employee Earnings</x-nav-link>
                <x-nav-link :href="route('deduction-types.index')" :active="request()->routeIs('deduction-types.*')">Deduction Types</x-nav-link>
                <x-nav-link :href="route('employee-deduction-assignments.index')" :active="request()->routeIs('employee-deduction-assignments.*')">Employee Deductions</x-nav-link>

                <div class="px-3 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Statutory</div>
                <x-nav-link :href="route('tax-brackets.index')" :active="request()->routeIs('tax-brackets.*')">Tax Brackets</x-nav-link>
                <x-nav-link :href="route('contribution-rates.index')" :active="request()->routeIs('contribution-rates.*')">Contribution Rates</x-nav-link>
            @endcan

            @can('attendance.manage')
                <div class="px-3 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Attendance</div>
                <x-nav-link :href="route('attendance-records.index')" :active="request()->routeIs('attendance-records.*')">Attendance Records</x-nav-link>
                <x-nav-link :href="route('attendance-adjustments.index')" :active="request()->routeIs('attendance-adjustments.*')">Adjustments</x-nav-link>
            @endcan

            @can('leave.manage')
                <div class="px-3 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Leave</div>
                <x-nav-link :href="route('leave-applications.index')" :active="request()->routeIs('leave-applications.*')">Leave Applications</x-nav-link>
                <x-nav-link :href="route('leave-balances.index')" :active="request()->routeIs('leave-balances.*')">Leave Balances</x-nav-link>
                <x-nav-link :href="route('leave-transactions.index')" :active="request()->routeIs('leave-transactions.*')">Leave Transactions</x-nav-link>
                <x-nav-link :href="route('leave-types.index')" :active="request()->routeIs('leave-types.*')">Leave Types</x-nav-link>
            @endcan

            @can('payroll.view')
                <div class="px-3 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Payroll</div>
                <x-nav-link :href="route('payroll-periods.index')" :active="request()->routeIs('payroll-periods.*')">Payroll Periods</x-nav-link>
                <x-nav-link :href="route('payroll-runs.index')" :active="request()->routeIs('payroll-runs.*') || request()->routeIs('payroll-details.*')">Monthly Payroll</x-nav-link>
                @can('payroll.approve')
                    <x-nav-link :href="route('payroll-runs.index', ['status' => 'PENDING_APPROVAL'])" :active="request()->query('status') === 'PENDING_APPROVAL'">Pending Approvals</x-nav-link>
                @endcan
                <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">Reports</x-nav-link>
                @can('blockchain.verify')
                    <x-nav-link :href="route('blockchain.index')" :active="request()->routeIs('blockchain.*') || request()->routeIs('blockchain-anchors.*')">Blockchain Verification</x-nav-link>
                @endcan
            @endcan

            @can('users.manage')
                <div class="px-3 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wider text-emerald-300">Administration</div>
                <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">User Accounts</x-nav-link>
            @endcan
        </nav>

        <div class="border-t border-white/10 px-5 py-4 text-xs">
            <div class="font-semibold">{{ auth()->user()->username }}</div>
            <div class="text-emerald-200">{{ auth()->user()->roles->pluck('role_name')->implode(', ') ?: 'No role' }}</div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
            <h1 class="text-lg font-semibold text-slate-800">@yield('heading', 'Dashboard')</h1>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    Logout
                </button>
            </form>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-6 py-7">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

</div>
</body>
</html>
