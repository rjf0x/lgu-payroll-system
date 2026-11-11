@props(['label', 'value'])
@php
    [$icon, $description] = match ($label) {
        'Active employees' => ['Organization', 'Distinct employees with an active employment record effective today.'],
        'Departments' => ['Organization', 'Total registered departments, including those with no employees.'],
        'Active accounts' => ['Administration', 'User accounts marked active.'],
        'Pending payroll tasks' => ['Payroll', auth()->user()->can('payroll.prepare')
            ? (auth()->user()->can('payroll.finalize')
                ? 'Draft, returned, rejected, and approved runs awaiting preparation, correction, or finalization.'
                : 'Draft, returned, and rejected runs awaiting preparation or correction.')
            : 'Approved runs awaiting finalization.'],
        'Finalized runs' => ['Statutory', 'All payroll runs marked finalized across every payroll period.'],
        default => ['Dashboard', null],
    };
@endphp
<div {{ $attributes->class(['ui-card ui-card-interactive']) }}>
    <dt class="flex items-center gap-3 text-sm font-semibold text-muted"><span class="ui-icon-tile"><x-nav-icon :name="$icon" /></span>{{ $label }}</dt>
    <dd class="ui-stat-value mt-4">{{ number_format($value) }}</dd>
    @if ($description)
        <dd class="mt-2 text-sm text-muted">{{ $description }}</dd>
    @endif
</div>
