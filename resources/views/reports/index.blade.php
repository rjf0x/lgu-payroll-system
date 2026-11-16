@extends('layouts.app')

@section('title', 'Reports')
@section('heading', 'Reports')

@section('content')
    <x-page-header description="Find a payroll period, review its scope, and open the register, department summary, deductions report, or payslips." />
    <form method="GET" action="{{ route('reports.index') }}" class="no-print mb-6 flex flex-wrap items-end gap-3">
        <x-form.input name="q" label="Payroll period" :value="$filters['q'] ?? ''" placeholder="Search period name" />
        <x-form.select name="status" label="Run status" :selected="$filters['status'] ?? ''" :options="['' => 'All statuses', 'DRAFT' => 'Draft', 'PENDING_APPROVAL' => 'Pending approval', 'RETURNED' => 'Returned', 'REJECTED' => 'Rejected', 'APPROVED' => 'Approved', 'FINALIZED' => 'Finalized']" />
            <x-button icon="filter" type="submit">Apply filters</x-button>
            <x-button icon="reset" :href="route('reports.index')">Reset</x-button>
        </form>

    <section aria-labelledby="payroll-reports-heading" class="mb-8 min-w-0">
        <h2 id="payroll-reports-heading" class="mb-3 text-lg font-semibold">Payroll reports</h2>
        <x-results :items="$runs" />
        <x-table label="Payroll reports by period">
            <thead><tr><th scope="col" class="ui-table-primary">Period / pay date</th><th scope="col">Run</th><th scope="col" class="text-center">Status</th><th scope="col" class="text-right tabular-nums">Employees</th><th scope="col" class="text-right ui-table-actions">Available reports</th></tr></thead>
            <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td class="ui-table-primary">{{ $run->payrollPeriod->period_name }}<span class="ui-table-secondary">Pay date {{ $run->payrollPeriod->pay_date?->format('M d, Y') }}</span></td>
                        <td>#{{ $run->run_number }}</td>
                        <td class="text-center"><x-status :value="$run->status" /></td>
                        <td class="text-right tabular-nums">{{ $run->details_count }}</td>
                        <td class="text-right ui-table-actions">
                            <x-action-menu :label="'Reports for payroll run #'.$run->run_number">
                                <x-button icon="document" :href="route('payroll-runs.register', $run)" aria-label="Register for {{ $run->payrollPeriod->period_name }}, run {{ $run->run_number }}">Register</x-button>
                                <x-button icon="list" :href="route('reports.payroll-summary', $run)">Department summary</x-button>
                                <x-button icon="list" :href="route('reports.deductions', $run)">Deductions report</x-button>
                                <x-button icon="document" :href="route('payroll-runs.payslips', $run)">Payslips</x-button>
                                @can('reports.generate')<x-button icon="download" variant="secondary" href="{{ route('payroll-runs.register.export', $run) }}">Export spreadsheet</x-button>@endcan
                            </x-action-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No payroll runs match these filters.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        <div class="mt-4">{{ $runs->links() }}</div>
    </section>

    <section aria-labelledby="report-history-heading" class="min-w-0">
        <h2 id="report-history-heading" class="mb-3 text-lg font-semibold">Export history</h2>
        <p class="mb-3 text-muted">History records when an export was requested. Reopening a register shows current data; an archived export file is not retained.</p>
        <x-results :items="$reports" />
        <x-table label="Generated report history">
            <thead><tr><th scope="col" class="ui-table-primary">Report</th><th scope="col">Payroll scope</th><th scope="col">Requested by</th><th scope="col">Generated at</th></tr></thead>
            <tbody>
                @forelse ($reports as $report)
                    @php($reportRun = $reportScopes->get($report->getKey()))
                    <tr>
                        <td class="ui-table-primary">{{ \Illuminate\Support\Str::headline(strtolower($report->report_type)) }}<span class="ui-table-secondary">CSV export request</span></td>
                        <td class="ui-wrap">
                            @if ($reportRun)
                                <a href="{{ route('payroll-runs.register', $reportRun) }}">{{ $reportRun->payrollPeriod->period_name }} ? Run #{{ $reportRun->run_number }}</a>
                            @else
                                Payroll scope unavailable
                            @endif
                        </td>
                        <td>{{ $report->generatedBy?->username ?? 'Unavailable account' }}</td>
                        <td><x-local-time :value="$report->generated_at" show-timezone /></td>
                    </tr>
                @empty
                    <tr><td colspan="4">No reports generated yet.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        <div class="mt-4">{{ $reports->links() }}</div>
    </section>
@endsection
