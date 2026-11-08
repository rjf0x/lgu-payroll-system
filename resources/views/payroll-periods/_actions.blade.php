<div class="flex flex-wrap items-center justify-end gap-2">
    @can('payroll.view')
        <a href="{{ route('payroll-periods.show', $period) }}" class="ui-button ui-button-secondary" aria-label="View details for {{ $period->period_name }}">View Details</a>
        @if ($period->runs_count > 0)
            <a href="{{ route('payroll-runs.index', ['payroll_period_id' => $period->getKey()]) }}" class="ui-button ui-button-secondary">View Payroll Runs</a>
        @endif
    @endcan
    @can('payroll.prepare')
        @if ($period->status === 'OPEN' && $period->runs_count === 0)
            <a href="{{ route('payroll-periods.edit', $period) }}" class="ui-button ui-button-secondary">Edit Period</a>
        @endif
        @if ($period->status === 'OPEN')
            <form method="POST" action="{{ route('payroll-periods.close', $period) }}" data-confirm data-confirm-title="Close payroll period" data-confirm-description="New payroll runs will be blocked. Existing runs will be preserved." data-confirm-context="{{ $period->period_name }}" data-submit-feedback>
                @csrf
                <button type="submit" class="ui-button ui-button-secondary">Close Period</button>
            </form>
        @endif
    @endcan
</div>
