@extends('layouts.app')
@section('title', ($dashboardRole ?? 'Account').' Dashboard')
@section('heading', ($dashboardRole ?? 'Account').' Dashboard')
@section('content')
<div class="flex flex-col gap-6">
    <section>
        <h2 class="ui-section-heading">Welcome, {{ auth()->user()->username }}</h2>
        <p class="mt-1 max-w-2xl text-sm text-muted">
            @switch($dashboardRole)
                @case('Admin') Manage system access, configuration, and records oversight. @break
                @case('Payroll Officer') Maintain payroll inputs, prepare computations, and submit payroll for independent approval. @break
                @case('Approver') Review submitted payroll and make independent approval decisions. @break
                @case('Viewer') Browse finalized payroll records, employee information, and authorized reports. @break
                @default No role is assigned. Contact your administrator to request access.
            @endswitch
        </p>
    </section>
    @can('users.manage')
        <section aria-labelledby="access-configuration-heading">
            <h2 id="access-configuration-heading" class="ui-section-heading mb-4">Access and configuration</h2>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <x-card class="flex flex-col items-start gap-4">
                    <span class="ui-icon-tile">
                        <x-nav-icon name="Administration" />
                    </span>
                    <div>
                        <h3 class="ui-section-heading">User access</h3>
                        <p class="mt-2 text-sm text-muted">Manage user accounts, assign roles, and control access to the system.</p>
                    </div>
                    <div class="mt-auto pt-2"><x-button :href="route('users.index')" variant="primary">Manage user access</x-button></div>
                </x-card>
                @can('audit.view')
                    <x-card class="flex flex-col items-start gap-4">
                        <span class="ui-icon-tile">
                            <x-nav-icon name="Statutory" />
                        </span>
                        <div>
                            <h3 class="ui-section-heading">Audit trail</h3>
                            <p class="mt-2 text-sm text-muted">Review recorded activity and track changes across the system.</p>
                        </div>
                        <div class="mt-auto pt-2"><x-button :href="route('audit-logs.index')">Review audit trail</x-button></div>
                    </x-card>
                @endcan
                @can('compensation.manage')
                    <x-card class="flex flex-col items-start gap-4">
                        <span class="ui-icon-tile">
                            <x-nav-icon name="Compensation" />
                        </span>
                        <div>
                            <h3 class="ui-section-heading">Salary schedules</h3>
                            <p class="mt-2 text-sm text-muted">Review salary grades, steps, and pay rates used in payroll calculations.</p>
                        </div>
                        <div class="mt-auto pt-2"><x-button :href="route('salary-schedules.index')">Review salary schedules</x-button></div>
                    </x-card>
                @endcan
            </div>
        </section>
    @endcan
    @if ($nextActions)
        <section aria-labelledby="next-actions-heading">
            <h2 id="next-actions-heading" class="ui-section-heading mb-4">Next actions</h2>
            <div class="grid auto-rows-fr gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($nextActions as $action)
                    <x-card :class="'flex h-full flex-col items-start border-t-4 '.($action['title'] === 'Payroll preparation' ? 'border-t-brand ring-1 ring-brand-border' : 'border-t-slate-200')">
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
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 ring-1 ring-inset ring-amber-200">Needs attention</span>
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
                        <x-button :href="$action['href']" :variant="$action['title'] === 'Payroll preparation' || $action['count'] > 0 ? 'primary' : 'secondary'" class="mt-auto w-full text-center">{{ $action['label'] }}</x-button>
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
                    <x-card class="flex h-full flex-col items-start gap-4">
                        <span class="ui-icon-tile"><x-nav-icon name="Payroll" /></span>
                        <div>
                            <h3 class="ui-section-heading">Finalized Payroll</h3>
                            <p class="mt-2 text-sm text-muted">Browse completed payroll transactions and review their employee breakdowns and authorized payroll documents.</p>
                        </div>
                        <div class="mt-auto pt-2"><x-button :href="route('payroll-runs.index', ['status' => 'FINALIZED'])" variant="primary">Browse finalized payroll</x-button></div>
                    </x-card>
                @endcan
                @can('employees.view')
                    <x-card class="flex h-full flex-col items-start gap-4">
                        <span class="ui-icon-tile"><x-nav-icon name="Organization" /></span>
                        <div>
                            <h3 class="ui-section-heading">Employee Directory</h3>
                            <p class="mt-2 text-sm text-muted">Find authorized employee information, employment records, and related details without changing records.</p>
                        </div>
                        <div class="mt-auto pt-2"><x-button :href="route('employees.index')" variant="primary">View employee directory</x-button></div>
                    </x-card>
                @endcan
            </div>
        </section>
    @endif
    @if ($metrics)
        <section aria-labelledby="dashboard-statistics-heading">
        <h2 id="dashboard-statistics-heading" class="ui-section-heading mb-4">Dashboard statistics</h2>
        <dl @class(['grid auto-rows-fr grid-cols-1 gap-4 sm:grid-cols-2', 'xl:grid-cols-5' => count($metrics) === 5, 'xl:grid-cols-4' => count($metrics) === 4, 'xl:grid-cols-3' => count($metrics) === 3])>
            @foreach ($metrics as $label => $value)
                <x-dashboard-stat :label="$label" :value="$value" />
            @endforeach
        </dl>
        </section>
    @endif
    <section class="ui-card">
        <h2 class="ui-section-heading">Quick actions</h2>
        <div class="mt-4 grid auto-rows-fr gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @can('users.manage') <x-dashboard-action :href="route('users.create')" icon="Administration">Add user</x-dashboard-action> @endcan
            @can('employees.view') <x-dashboard-action :href="route('employees.index')" icon="Organization">Employee directory</x-dashboard-action> @endcan
            @can('compensation.manage') <x-dashboard-action :href="route('salary-schedules.index')" icon="Compensation">Salary schedules</x-dashboard-action> @endcan
            @can('payroll.prepare') <x-dashboard-action :href="route('payroll-runs.create')" icon="Payroll">Prepare payroll</x-dashboard-action> @endcan
            @can('payroll.approve') <x-dashboard-action :href="route('payroll-runs.index', ['status' => 'PENDING_APPROVAL'])" icon="Statutory">Pending approvals</x-dashboard-action> @endcan
            @can('payroll.view') <x-dashboard-action :href="route('reports.index')" icon="Statutory">View reports</x-dashboard-action> @endcan
            @can('blockchain.verify') <x-dashboard-action :href="route('blockchain.index')" icon="Administration">Blockchain verification</x-dashboard-action> @endcan
        </div>
    </section>
    @if ($roles->isNotEmpty())
        <section aria-labelledby="system-user-overview-heading">
            <h2 id="system-user-overview-heading" class="ui-section-heading">System user overview</h2>
            <p class="mt-1 text-sm text-muted">Accounts by assigned role. Accounts with multiple roles appear in each role.</p>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-surface shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
                <div>
                    <h2 class="ui-section-heading">{{ $dashboardRole === 'Approver' ? 'Payroll awaiting your review' : ($dashboardRole === 'Viewer' ? 'Finalized payroll records' : 'Recent payroll runs') }}</h2>
                    <p class="mt-1 text-sm text-muted">The five latest payroll transactions you can access, ordered by finalization or processing date, then creation date for unprocessed runs.</p>
                </div>
                <a href="{{ route('payroll-runs.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand hover:underline">View All Payroll Runs</a>
            </div>
            <x-table label="Dashboard payroll runs" class="rounded-none border-x-0 border-b-0 shadow-none">
                    <thead class="border-y border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Coverage period</th>
                            <th scope="col" class="px-4 py-3 text-right">Run number</th>
                            <th scope="col" class="px-4 py-3 text-right">Employees</th>
                            <th scope="col" class="px-4 py-3">Processing status</th>
                            <th scope="col" class="px-4 py-3">Date finalized</th>
                            <th scope="col" class="px-4 py-3 text-right">Records</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($runs as $run)
                            <tr>
                                <td class="px-4 py-2 font-medium"><time datetime="{{ $run->payrollPeriod->start_date->toDateString() }}">{{ $run->payrollPeriod->start_date->format('M d, Y') }}</time> &ndash; <time datetime="{{ $run->payrollPeriod->end_date->toDateString() }}">{{ $run->payrollPeriod->end_date->format('M d, Y') }}</time></td>
                                <td class="px-4 py-2 text-right tabular-nums">#{{ $run->run_number }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ number_format($run->details_count) }}</td>
                                <td class="px-4 py-2"><x-status :value="$run->status" /></td>
                                <td class="px-4 py-2 text-muted">
                                    @if ($run->finalized_at)
                                        <time datetime="{{ $run->finalized_at->toIso8601String() }}">{{ $run->finalized_at->format('M d, Y') }}</time>
                                    @else
                                        <span>Not finalized</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right"><x-button :href="route('payroll-runs.show', $run)" :aria-label="'View payroll records for run #'.$run->run_number.', '.$run->payrollPeriod->start_date->format('M d, Y').' to '.$run->payrollPeriod->end_date->format('M d, Y')">View records</x-button></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-muted">No payroll runs to display.</td></tr>
                        @endforelse
                    </tbody>
            </x-table>
            @if ($runs->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-3 text-sm text-muted">
                    <p>Showing {{ $runs->count() }} latest {{ $runs->count() === 1 ? 'run' : 'runs' }}.</p>
                    <a href="{{ route('payroll-runs.index') }}" class="inline-flex min-h-11 items-center font-semibold text-brand hover:underline">Browse older payroll records &rarr;</a>
                </div>
            @endif
        </section>
    @endcan
</div>
@endsection
