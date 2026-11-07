@props(['run', 'canSubmit', 'canReview', 'canFinalize', 'independent'])

@php
    $stage = match ($run->status) {
        'DRAFT', 'RETURNED', 'REJECTED', 'PROCESSING' => 0,
        'PENDING_APPROVAL' => 1,
        'APPROVED' => 2,
        'FINALIZED' => 3,
        default => null,
    };
    $guidance = match ($run->status) {
        'DRAFT', 'RETURNED', 'REJECTED' => $run->details->isEmpty()
            ? (auth()->user()->can('payroll.prepare') ? 'Compute payroll to create the employee breakdown, then check the totals before submitting.' : 'A payroll preparer must compute this run before it can be submitted.')
            : ($canSubmit ? 'Review the employee breakdown and totals, then submit for independent approval. Recompute only if the payroll inputs have changed.' : 'This run is open for correction. A user with submission permission must send it for independent review.'),
        'PROCESSING' => 'Payroll computation is in progress. Review the employee breakdown when processing completes.',
        'PENDING_APPROVAL' => ! $independent ? 'You prepared or submitted this run. Another user must review it to preserve separation of duties.'
            : ($canReview ? 'Check the totals and employee breakdown. Approve the run, or explain the corrections needed when returning or rejecting it.' : 'This run is awaiting an independent reviewer with the appropriate permission.'),
        'APPROVED' => $canFinalize ? 'The run is approved. Finalization locks it against editing and recomputation. Confirm the period and totals before proceeding.' : 'The run is approved and awaits a user with finalization permission.',
        'FINALIZED' => 'This run is finalized. Payroll details are locked; you can open the authorized reports and documents below.',
        'CANCELLED' => 'This run is cancelled and cannot proceed through the approval workflow.',
        default => 'Review the run status before proceeding.',
    };
@endphp

<x-card heading="Payroll progress">
    <ol class="mb-4 grid grid-cols-2 gap-3 text-sm lg:grid-cols-4" aria-label="Payroll stages">
        @foreach (['Prepare and compute', 'Independent review', 'Approved', 'Finalized'] as $index => $label)
            <li @if ($stage === $index) aria-current="step" @endif @class(['border-b-4 pb-2', 'border-brand font-semibold text-brand' => $stage !== null && $index <= $stage, 'border-slate-200 text-muted' => $stage === null || $index > $stage])>
                {{ $index + 1 }}. {{ $label }}@if ($stage === $index)<span class="sr-only"> (current stage)</span>@endif
            </li>
        @endforeach
    </ol>
    <p class="text-sm text-muted">{{ $guidance }}</p>
    @if (in_array($run->status, ['RETURNED', 'REJECTED'], true))
        @php($feedback = $run->approvals->where('action', $run->status)->sortByDesc('acted_at')->first())
        @if ($feedback?->remarks)
            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <h3 class="font-semibold">Corrections requested</h3>
                <p class="mt-1 whitespace-pre-line">{{ $feedback->remarks }}</p>
            </div>
        @endif
    @endif
</x-card>
