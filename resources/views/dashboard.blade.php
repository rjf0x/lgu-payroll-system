@extends('layouts.app')
@section('title', ($dashboardRole ?? 'Account').' Dashboard')
@section('heading', ($dashboardRole ?? 'Account').' Dashboard')
@section('content')
<div class="flex flex-col gap-8">
    <section class="dashboard-hero" aria-labelledby="dashboard-welcome-heading">
        <p class="ui-eyebrow">Operations overview</p>
        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="dashboard-welcome-heading" class="text-2xl font-semibold tracking-tight text-ink sm:text-3xl">Welcome, {{ auth()->user()->username }}</h2>
                <p class="mt-2 max-w-2xl text-base leading-7 text-muted">
            @switch($dashboardRole)
                @case('Admin') Manage system access, configuration, and records oversight. @break
                @case('Payroll Officer') Maintain payroll inputs, prepare computations, and submit payroll for independent approval. @break
                @case('Approver') Review submitted payroll and make independent approval decisions. @break
                @case('Viewer') Browse finalized payroll records, employee information, and authorized reports. @break
                @default No role is assigned. Contact your administrator to request access.
            @endswitch
                </p>
            </div>
            <span class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-800">
                <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                {{ $dashboardRole ?? 'Account' }} workspace
            </span>
        </div>
    </section>
    @can('users.manage')
        <section aria-labelledby="access-configuration-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="ui-eyebrow">Governance</p>
                    <h2 id="access-configuration-heading" class="ui-section-heading mt-1">Access and configuration</h2>
                </div>
                <p class="ui-muted-label">Manage the control plane</p>
            </div>
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <x-card class="ui-card-interactive flex flex-col items-start gap-4">
                    <span class="ui-icon-tile">
                        <x-nav-icon name="Administration" />
                    </span>
                    <div>
                        <h3 class="ui-section-heading">User access</h3>
                        <p class="mt-2 text-sm text-muted">Manage user accounts, assign roles, and control access to the system.</p>
                    </div>
                    <div class="mt-auto pt-2"><x-button icon="users" :href="route('users.index')" variant="primary">Manage user access</x-button></div>
                </x-card>
                @can('audit.view')
                    <x-card class="ui-card-interactive flex flex-col items-start gap-4">
                        <span class="ui-icon-tile">
                            <x-nav-icon name="Statutory" />
                        </span>
                        <div>
                            <h3 class="ui-section-heading">Audit trail</h3>
                            <p class="mt-2 text-sm text-muted">Review recorded activity and track changes across the system.</p>
                        </div>
                        <div class="mt-auto pt-2"><x-button icon="list" :href="route('audit-logs.index')">Review audit trail</x-button></div>
                    </x-card>
                @endcan
                @can('compensation.manage')
                    <x-card class="ui-card-interactive flex flex-col items-start gap-4">
                        <span class="ui-icon-tile">
                            <x-nav-icon name="Compensation" />
                        </span>
                        <div>
                            <h3 class="ui-section-heading">Salary schedules</h3>
                            <p class="mt-2 text-sm text-muted">Review salary grades, steps, and pay rates used in payroll calculations.</p>
                        </div>
                        <div class="mt-auto pt-2"><x-button icon="list" :href="route('salary-schedules.index')">Review salary schedules</x-button></div>
                    </x-card>
                @endcan
            </div>
        </section>
    @endcan
    @if ($nextActions)
        <section aria-labelledby="next-actions-heading">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="ui-eyebrow">Workflow queue</p>
                    <h2 id="next-actions-heading" class="ui-section-heading mt-1">Next actions</h2>
                </div>
                <p class="ui-muted-label">Prioritized by your permissions</p>
            </div>
            <div class="grid auto-rows-fr gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($nextActions as $action)
                    <x-card :class="'dashboard-action-card flex h-full flex-col items-start border-t-4 '.($action['title'] === 'Payroll preparation' ? 'border-t-brand ring-1 ring-brand-border' : 'border-t-slate-200')">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="ui-icon-tile">
                                <x-nav-icon :name="[
                                    'Payroll preparation' => 'Payroll',
                                    'Payroll corrections' => 'Earnings & Deductions',
                                    'Ready to submit' => 'Statutory',
                                    'Independent review' => 'Administration',
                                    'Ready to finalize' => 'Statutory',
                                ][$action['title']] ?? 'Dashboard'" />
                            </span>
                            <h3 class="ui-section-heading">{{ $action['title'] }}</h3>
                        </div>
                        @if ($action['count'] > 0)
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="text-5xl font-bold tabular-nums text-brand">{{ number_format($action['count']) }}</p>
                                <span class="ui-attention-badge">Needs attention</span>
                            </div>
                            <p class="mb-5 mt-3 text-sm text-muted">{{ $action['description'] }}</p>
                        @else
                            <p class="text-3xl font-semibold tabular-nums text-muted">0</p>
                            <p class="mt-3 text-sm font-semibold text-muted">No items pending</p>
                            <p class="mb-5 mt-1 text-sm text-muted">
                                @switch($action['title'])
                                    @case('Payroll preparation') No draft runs need preparation. You can start a new payroll when ready. @break
                                    @case('Payroll corrections') No returned or rejected runs need correction. Feedback will appear in the payroll record when a reviewer requests changes. @break
                                    @case('Ready to submit') No draft runs are ready for submission. Complete and confirm every employee's payroll inputs first. @break
                                    @case('Independent review') No submitted payroll runs are awaiting your independent review. @break
                                    @case('Ready to finalize') No approved payroll runs are awaiting finalization. @break
                                    @default New items will appear here when they need your attention.
                                @endswitch
                            </p>
                        @endif
                        <x-button icon="list" :href="$action['href']" :variant="$action['title'] === 'Payroll preparation' || $action['count'] > 0 ? 'primary' : 'secondary'" class="mt-auto w-full text-center">{{ $action['label'] }}</x-button>
                    </x-card>
                @endforeach
            </div>
        </section>
    @endif
    @if ($dashboardRole === 'Viewer')
        <section aria-labelledby="payroll-records-reports-heading">
            <h2 id="payroll-records-reports-heading" class="ui-section-heading">Payroll Records and Reports</h2>
            <p class="mb-4 mt-1 text-sm text-muted">Access finalized payroll transactions, authorized employee information, and available payroll reports. Your access is read-only.</p>
            <div class="grid auto-rows-fr gap-4 md:grid-cols-2">
                @can('payroll.view')
                    <x-card class="ui-card-interactive flex h-full flex-col items-start gap-4">
                        <span class="ui-icon-tile"><x-nav-icon name="Payroll" /></span>
                        <div>
                            <h3 class="ui-section-heading">Finalized Payroll</h3>
                            <p class="mt-2 text-sm text-muted">Browse completed payroll transactions and review their employee breakdowns and authorized payroll documents.</p>
                        </div>
                        <div class="mt-auto pt-2"><x-button icon="list" :href="route('payroll-runs.index', ['status' => 'FINALIZED'])" variant="primary">Browse finalized payroll</x-button></div>
                    </x-card>
                @endcan
                @can('employees.view')
                    <x-card class="ui-card-interactive flex h-full flex-col items-start gap-4">
                        <span class="ui-icon-tile"><x-nav-icon name="Organization" /></span>
                        <div>
                            <h3 class="ui-section-heading">Employee Directory</h3>
                            <p class="mt-2 text-sm text-muted">Find authorized employee information, employment records, and related details without changing records.</p>
                        </div>
                        <div class="mt-auto pt-2"><x-button icon="view" :href="route('employees.index')" variant="primary">View employee directory</x-button></div>
                    </x-card>
                @endcan
            </div>
        </section>
    @endif
    @if ($metrics)
        <section aria-labelledby="dashboard-statistics-heading">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="ui-eyebrow">At a glance</p>
                <h2 id="dashboard-statistics-heading" class="ui-section-heading mt-1">Dashboard statistics</h2>
            </div>
            <p class="ui-muted-label">Current system totals</p>
        </div>
        <dl @class(['grid auto-rows-fr grid-cols-1 gap-5 sm:grid-cols-2', 'xl:grid-cols-5' => count($metrics) === 5, 'xl:grid-cols-4' => count($metrics) === 4, 'xl:grid-cols-3' => count($metrics) === 3])>
            @foreach ($metrics as $label => $value)
                <x-dashboard-stat :label="$label" :value="$value" />
            @endforeach
        </dl>
        </section>
    @endif
    <section class="ui-card ui-card-quiet">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="ui-eyebrow">Shortcuts</p>
                <h2 class="ui-section-heading mt-1">Quick actions</h2>
            </div>
            <p class="ui-muted-label">Common tasks</p>
        </div>
        <div class="dashboard-action-grid mt-5">
            @can('users.manage') <x-dashboard-action :href="route('users.create')" icon="Administration">Add user</x-dashboard-action> @endcan
            @can('employees.view') <x-dashboard-action :href="route('employees.index')" icon="Organization">Employee directory</x-dashboard-action> @endcan
            @can('compensation.manage') <x-dashboard-action :href="route('salary-schedules.index')" icon="Compensation">Salary schedules</x-dashboard-action> @endcan
            @can('payroll.prepare') <x-dashboard-action :href="route('payroll-runs.create')" icon="Payroll">Prepare payroll</x-dashboard-action> @endcan
            @can('payroll.approve') <x-dashboard-action :href="route('payroll-runs.index', ['status' => 'PENDING_APPROVAL'])" icon="Statutory">Pending approvals</x-dashboard-action> @endcan
            @can('payroll.view') <x-dashboard-action :href="route('reports.index')" icon="Statutory">View reports</x-dashboard-action> @endcan
            @can('blockchain.verify') <x-dashboard-action :href="route('blockchain.index')" icon="Administration">Payroll verification</x-dashboard-action> @endcan
        </div>
    </section>
    @if ($roles->isNotEmpty())
        <section aria-labelledby="system-user-overview-heading">
            <h2 id="system-user-overview-heading" class="ui-section-heading">System user overview</h2>
            <p class="mt-1 text-sm text-muted">Accounts by assigned role. Accounts with multiple roles appear in each role.</p>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <div class="ui-card border-t-4 border-t-brand sm:col-span-2 xl:col-span-4">
                    <dt class="flex items-center gap-3 text-sm font-semibold text-muted"><span class="ui-icon-tile"><x-nav-icon name="Organization" /></span>Total unique users</dt>
                    <dd class="ui-stat-value mt-4">{{ number_format($totalUniqueUsers) }}</dd>
                    <dd class="mt-2 text-sm text-muted">Each account counted once, including inactive accounts and accounts without a role.</dd>
                </div>
                @foreach ($roles as $role)
                    <div class="ui-card">
                        <dt class="flex items-center gap-3 text-sm font-semibold text-muted">
                            <span class="ui-icon-tile"><x-nav-icon :name="['Admin' => 'Administration', 'Payroll Officer' => 'Payroll', 'Approver' => 'Statutory', 'Viewer' => 'Dashboard'][$role->role_name] ?? 'Organization'" /></span>
                            {{ $role->role_name }}
                        </dt>
                        <dd class="ui-stat-value mt-4">{{ number_format($role->users_count) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif
    @can('payroll.view')
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-surface shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 bg-slate-50/60 p-5 sm:p-6">
                <div>
                    <p class="ui-eyebrow">Recent activity</p>
                    <h2 class="ui-section-heading">{{ $dashboardRole === 'Approver' ? 'Payroll awaiting your review' : ($dashboardRole === 'Viewer' ? 'Finalized payroll records' : 'Recent payroll runs') }}</h2>
                    <p class="mt-1 text-sm text-muted">The five latest payroll transactions you can access, ordered by finalization or processing date, then creation date for unprocessed runs.</p>
                </div>
                <x-button icon="view" :href="route('payroll-runs.index')">View All Payroll Runs</x-button>
            </div>
            <x-table label="Dashboard payroll runs" class="rounded-none border-x-0 border-b-0 shadow-none">
                    <thead class="border-y border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 ui-table-primary">Coverage period</th>
                            <th scope="col" class="px-4 py-3 text-left">Run number</th>
                            <th scope="col" class="px-4 py-3 text-right tabular-nums">Employees</th>
                            <th scope="col" class="px-4 py-3 text-center">Processing status</th>
                            <th scope="col" class="px-4 py-3">Date finalized</th>
                            <th scope="col" class="px-4 py-3 text-right ui-table-actions">Records</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr>
                                <td class="px-4 py-2 font-medium ui-table-primary"><time datetime="{{ $run->payrollPeriod->start_date->toDateString() }}">{{ $run->payrollPeriod->start_date->format('M d, Y') }}</time> &ndash; <time datetime="{{ $run->payrollPeriod->end_date->toDateString() }}">{{ $run->payrollPeriod->end_date->format('M d, Y') }}</time></td>
                                <td class="px-4 py-2 tabular-nums text-left">#{{ $run->run_number }}</td>
                                <td class="px-4 py-2 tabular-nums text-right">{{ number_format($run->details_count) }}</td>
                                <td class="px-4 py-2 text-center"><x-status :value="$run->status" /></td>
                                <td class="px-4 py-2 text-muted">
                                    @if ($run->finalized_at)
                                        <x-local-time :value="$run->finalized_at" format="M d, Y" />
                                    @else
                                        <span>Not finalized</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right ui-table-actions"><x-action-menu :label="'Actions for payroll run #'.$run->run_number"><x-button icon="view" :href="route('payroll-runs.show', $run)" :aria-label="'View payroll records for run #'.$run->run_number.', '.$run->payrollPeriod->start_date->format('M d, Y').' to '.$run->payrollPeriod->end_date->format('M d, Y')">View records</x-button></x-action-menu></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-muted">No payroll runs to display.</td></tr>
                        @endforelse
                    </tbody>
            </x-table>
            @if ($runs->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-3 text-sm text-muted">
                    <p>Showing {{ $runs->count() }} latest {{ $runs->count() === 1 ? 'run' : 'runs' }}.</p>
                    <x-button icon="list" :href="route('payroll-runs.index')" class="ui-button-compact">Browse older payroll records &rarr;</x-button>
                </div>
            @endif
        </section>
    @endcan
</div>
@endsection
