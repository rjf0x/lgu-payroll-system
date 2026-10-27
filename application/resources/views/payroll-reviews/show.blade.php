@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-batches.show', $batch) }}" class="text-teal-800 underline">Monthly payroll and inputs</a>
    <h1 class="mt-5 text-3xl font-bold">Payroll review · {{ $batch->period_start->format('F Y') }}</h1>
    <p class="mt-3 font-semibold">Status: {{ ucfirst($batch->status) }} · {{ $batch->is_demonstration ? 'Demonstration only — not for real payroll' : 'Real payroll processing blocked' }}</p>
    <p class="mt-2">A separate Approver must review and finalize payroll. Preparation and submission do not authorize approval.</p>
    <p class="mt-3">Payroll version {{ $batch->payroll_version }}. <a class="underline" href="{{ route('payroll-corrections.index', $batch) }}">Correction requests and preserved versions</a></p>
    @if ($issue)<p class="mt-5 rounded bg-amber-50 p-4 font-semibold text-amber-900">Needs review: {{ $issue }}</p>@endif
    @if ($finalization)
        <a href="{{ route('payroll-documents.index', $batch) }}" class="mt-4 inline-block font-semibold text-teal-800 underline">Finalized payroll register and payslips</a>
        <a href="{{ route('payroll-anchoring.show', [$batch, $finalization]) }}" class="mt-4 inline-block font-semibold text-teal-800 underline">Blockchain anchoring status and local commitment</a>
        <a href="{{ route('payroll-verification.show', [$batch, $finalization]) }}" class="mt-4 inline-block font-semibold text-teal-800 underline">Payroll integrity checks and history</a>
        <p class="mt-5 rounded bg-teal-50 p-4 font-semibold">Finalized version {{ $finalization->version }} · {{ $finalization->created_at->format('Y-m-d H:i') }}. Preserved values are locked. Blockchain anchoring has not been performed.</p>
    @elseif ($submission && in_array($batch->status, ['submitted', 'approved'], true))
        <p class="mt-5">Submission {{ $submission->revision }} · {{ $submission->created_at->format('Y-m-d H:i') }}. Inputs and coverage are locked. Changed sources or configuration require return and resubmission.</p>
    @endif
    @if ($snapshot)
        @include('payroll-reviews.snapshot', ['snapshotTitle' => $finalization ? 'Preserved finalized payroll' : ($token ? 'Current payroll ready for submission' : 'Fixed submitted payroll')])
    @endif
    @php
        $actions = [];
        if ($token && auth()->user()->can('submit', $batch)) $actions['submit'] = 'Submit for approval';
        if ($submission && $independentReviewer && auth()->user()->can('review', $batch)) {
            if ($batch->status === 'submitted') $actions['approve'] = 'Approve payroll';
            $actions['return'] = 'Return for correction';
        }
        if ($submission && $independentReviewer && auth()->user()->can('finalize', $batch)) $actions['finalize'] = 'Finalize and lock payroll';
    @endphp
    @foreach ($actions as $action => $label)
        <form method="POST" action="{{ route('payroll-reviews.transition', [$batch, $action]) }}" class="mt-6 rounded border bg-white p-6">
            @csrf
            <h2 class="text-xl font-semibold">{{ $label }}</h2>
            <input type="hidden" name="record_version" value="{{ $batch->record_version }}">
            @if ($token)<input type="hidden" name="review_token" value="{{ $token }}">@endif
            @if ($submission)<input type="hidden" name="submission_id" value="{{ $submission->id }}">@endif
            <label for="reason-{{ $action }}" class="mt-4 block font-semibold">{{ $action === 'return' ? 'Required correction reason' : 'Review reason or authority reference' }}</label>
            <textarea id="reason-{{ $action }}" name="reason" required maxlength="500" rows="3" class="mt-2 w-full rounded border-slate-300"></textarea>
            <label class="mt-4 flex items-start gap-3"><input type="checkbox" name="review_confirmed" value="1" required><span>I reviewed the employee amounts and this action. This is a demonstration only; actual payroll rules remain unvalidated.</span></label>
            <button class="mt-4 rounded bg-teal-800 px-4 py-3 font-semibold text-white">{{ $label }}</button>
        </form>
    @endforeach
    <section class="mt-8">
        <h2 class="text-xl font-semibold">Workflow history</h2>
        @forelse ($events as $event)
            <article class="mt-3 rounded border bg-white p-4"><h3 class="font-semibold">{{ ucfirst($event->action) }} · {{ $event->from_status }} → {{ $event->to_status }}</h3><p>{{ $event->actor_username }} · {{ $event->created_at->format('Y-m-d H:i') }}</p><p class="mt-2">{{ $event->reason }}</p></article>
        @empty<p class="mt-3">No workflow actions yet.</p>@endforelse
        <div class="mt-4">{{ $events->withQueryString()->links() }}</div>
    </section>
    <section class="mt-8">
        <h2 class="text-xl font-semibold">Preserved submission revisions</h2>
        @foreach ($submissions as $savedSubmission)
            <details class="mt-4 rounded border bg-white p-4"><summary class="cursor-pointer font-semibold">Submission {{ $savedSubmission->revision }} · {{ $savedSubmission->created_at->format('Y-m-d H:i') }}</summary>
                @include('payroll-reviews.snapshot', ['snapshot' => $savedSubmission->snapshot, 'snapshotTitle' => 'Preserved submission '.$savedSubmission->revision])
            </details>
        @endforeach
        <div class="mt-4">{{ $submissions->withQueryString()->links() }}</div>
    </section>
@endsection
