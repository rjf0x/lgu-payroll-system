@php
    $routeRun = request()->route('payroll_run');
    $pendingApprovals = auth()->user()->can('payroll.approve') && (
        (request()->routeIs('payroll-runs.index') && request()->query('status') === 'PENDING_APPROVAL')
        || (request()->routeIs('payroll-runs.show') && $routeRun instanceof \App\Models\PayrollRun && $routeRun->status === \App\Models\PayrollRun::STATUS_PENDING_APPROVAL)
    );
@endphp

<nav id="navigation-links" aria-label="Main navigation" class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4 text-sm">
    <div data-navigation-section>
        <p class="px-3 pb-2 pt-3 text-xs font-semibold uppercase tracking-widest text-blue-200">Workspace</p>
        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="Dashboard">Dashboard</x-nav-link>
    @can('payroll.view')
        <x-nav-group label="Payroll" :active="request()->routeIs('payroll-*', 'payslips.*', 'reports.*', 'blockchain*')">
            <x-nav-link :href="route('payroll-periods.index')" :active="request()->routeIs('payroll-periods.*')">Payroll Periods</x-nav-link>
            <x-nav-link :href="route('payroll-runs.index')" :active="! $pendingApprovals && request()->routeIs('payroll-runs.*', 'payroll-details.*', 'payslips.*')">Monthly Payroll</x-nav-link>
            @can('payroll.approve')
                <x-nav-link :href="route('payroll-runs.index', ['status' => 'PENDING_APPROVAL'])" :active="$pendingApprovals">Pending Approvals</x-nav-link>
            @endcan
            <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">Reports</x-nav-link>
            @can('blockchain.verify')
                <x-nav-link :href="route('blockchain.index')" :active="request()->routeIs('blockchain.*') || request()->routeIs('blockchain-anchors.*')">Payroll Verification</x-nav-link>
            @endcan
        </x-nav-group>
    @endcan



    </div>
    @can('employees.view')
        <div class="mt-4" data-navigation-section>
            <p class="px-3 pb-2 pt-3 text-xs font-semibold uppercase tracking-widest text-blue-200">Records &amp; setup</p>
    @can('employees.view')
        <x-nav-group label="Organization" :active="request()->routeIs('departments.*', 'positions.*', 'employees.*', 'employment-records.*')">
            <x-nav-link :href="route('departments.index')" :active="request()->routeIs('departments.*')">Departments</x-nav-link>
            <x-nav-link :href="route('positions.index')" :active="request()->routeIs('positions.*')">Positions</x-nav-link>
            <x-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">Employees</x-nav-link>
            <x-nav-link :href="route('employment-records.index')" :active="request()->routeIs('employment-records.*')">Employment Records</x-nav-link>
        </x-nav-group>
    @endcan

    @can('employees.view')
        <x-nav-group label="Compensation" :active="request()->routeIs('salary-schedules.*', 'salary-grade-steps.*', 'compensation-profiles.*')">
            <x-nav-link :href="route('salary-schedules.index')" :active="request()->routeIs('salary-schedules.*')">Salary Schedules</x-nav-link>
            <x-nav-link :href="route('salary-grade-steps.index')" :active="request()->routeIs('salary-grade-steps.*')">Salary Grade Steps</x-nav-link>
            <x-nav-link :href="route('compensation-profiles.index')" :active="request()->routeIs('compensation-profiles.*')">Compensation Profiles</x-nav-link>
        </x-nav-group>

        <x-nav-group label="Earnings & Deductions" :active="request()->routeIs('earning-types.*', 'employee-earning-assignments.*', 'deduction-types.*', 'employee-deduction-assignments.*')">
            <x-nav-link :href="route('earning-types.index')" :active="request()->routeIs('earning-types.*')">Earning Types</x-nav-link>
            <x-nav-link :href="route('employee-earning-assignments.index')" :active="request()->routeIs('employee-earning-assignments.*')">Employee Earnings</x-nav-link>
            <x-nav-link :href="route('deduction-types.index')" :active="request()->routeIs('deduction-types.*')">Deduction Types</x-nav-link>
            <x-nav-link :href="route('employee-deduction-assignments.index')" :active="request()->routeIs('employee-deduction-assignments.*')">Employee Deductions</x-nav-link>
        </x-nav-group>

        <x-nav-group label="Statutory" :active="request()->routeIs('tax-brackets.*', 'contribution-rates.*')">
            <x-nav-link :href="route('tax-brackets.index')" :active="request()->routeIs('tax-brackets.*')">Tax Brackets</x-nav-link>
            <x-nav-link :href="route('contribution-rates.index')" :active="request()->routeIs('contribution-rates.*')">Contribution Rates</x-nav-link>
        </x-nav-group>
    @endcan

        </div>
    @endcan

    @canany(['users.manage', 'audit.view', 'settings.manage'])
        <div class="mt-4" data-navigation-section>
            <x-nav-group label="Administration" :active="request()->routeIs('users.*', 'audit-logs.*', 'system-settings.*')">
                @can('users.manage')
                    <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">User Accounts</x-nav-link>
                @endcan
                @can('audit.view')
                    <x-nav-link :href="route('audit-logs.index')" :active="request()->routeIs('audit-logs.*')">Audit trail</x-nav-link>
                @endcan
                @can('settings.manage')
                    <x-nav-link :href="route('system-settings.edit')" :active="request()->routeIs('system-settings.*')">System settings</x-nav-link>
                @endcan
            </x-nav-group>
        </div>
    @endcanany
    <p id="navigation-no-results" hidden class="px-3 py-6 text-sm leading-6 text-brand-on-dark">No sections found. Try another keyword.</p>
</nav>
