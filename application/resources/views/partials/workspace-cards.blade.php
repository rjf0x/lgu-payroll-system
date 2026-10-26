<div class="workspace-cards">
    @can('viewAny', \App\Models\PayrollRuleVersion::class)
        <a href="{{ route('payroll-rule-versions.index') }}" class="workspace-card"><span class="card-icon"><x-ui.icon name="layers" /></span><span class="card-copy"><strong>Payroll configuration</strong><span>{{ auth()->user()->can('create', \App\Models\PayrollRuleVersion::class) ? 'Maintain dated settings, record activation authority and preserve changes.' : 'Review activated settings, source references and preserved configuration versions.' }}</span><span class="card-action">View configuration versions <x-ui.icon name="arrow" /></span></span></a>
    @endcan
    @can('viewAny', \App\Models\Employee::class)
        <a href="{{ route('employees.index') }}" class="workspace-card"><span class="card-icon"><x-ui.icon name="people" /></span><span class="card-copy"><strong>Employee records</strong><span>Find employee details, classifications and authorized salary history.</span><span class="card-action">{{ auth()->user()->role === \App\Enums\Role::PayrollOfficer ? 'Manage employees' : 'View employees' }} <x-ui.icon name="arrow" /></span></span></a>
    @endcan
    @can('viewAny', \App\Models\PayrollBatch::class)
        <a href="{{ route('payroll-batches.index') }}" class="workspace-card"><span class="card-icon"><x-ui.icon name="calendar" /></span><span class="card-copy"><strong>Monthly payroll drafts</strong><span>Open employee coverage, saved inputs and core calculation previews.</span><span class="card-action">Open payroll drafts <x-ui.icon name="arrow" /></span></span></a>
    @endcan
    @can('viewAny', \App\Models\SalarySchedule::class)
        <a href="{{ route('salary-schedules.index') }}" class="workspace-card"><span class="card-icon"><x-ui.icon name="layers" /></span><span class="card-copy"><strong>Salary schedules</strong><span>{{ auth()->user()->can('create', \App\Models\SalarySchedule::class) ? 'Maintain effective-dated grades, steps and externally authorized rates.' : 'View published grades, steps and effective-dated salary rates.' }}</span><span class="card-action">View salary schedules <x-ui.icon name="arrow" /></span></span></a>
    @endcan
    @can('manage-users')
        <a href="{{ route('users.index') }}" class="workspace-card"><span class="card-icon"><x-ui.icon name="shield" /></span><span class="card-copy"><strong>User accounts</strong><span>Manage user accounts, roles, passwords and account access.</span><span class="card-action">Manage user accounts <x-ui.icon name="arrow" /></span></span></a>
    @endcan
</div>
