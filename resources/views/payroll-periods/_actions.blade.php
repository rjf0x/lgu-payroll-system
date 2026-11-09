<div class="flex flex-wrap items-center justify-end gap-2">
    @can('payroll.view')
        <x-button icon="view" href="{{ route('payroll-periods.show', $period) }}" title="View Details" aria-label="View details for {{ $period->period_name }}">View Details</x-button>
        @if ($period->runs_count > 0)
            <x-button icon="list" href="{{ route('payroll-runs.index', ['payroll_period_id' => $period->getKey()]) }}" title="View Payroll Runs" aria-label="View payroll runs for {{ $period->period_name }}">View Payroll Runs</x-button>
        @endif
    @endcan
    @can('payroll.prepare')
        @if ($period->status === 'OPEN' && $period->runs_count === 0)
            <x-button icon="edit" href="{{ route('payroll-periods.edit', $period) }}" title="Edit Period" aria-label="Edit period {{ $period->period_name }}">Edit Period</x-button>
        @endif
        @if ($period->status === 'OPEN')
            <form method="POST" action="{{ route('payroll-periods.close', $period) }}" data-confirm data-confirm-title="Close payroll period" data-confirm-description="New payroll runs will be blocked. Existing runs will be preserved." data-confirm-context="{{ $period->period_name }}" data-submit-feedback>
                @csrf
                <x-button icon="lock" type="submit" title="Close Period" aria-label="Close period {{ $period->period_name }}">Close Period</x-button>
            </form>
        @endif
    @endcan
</div>
