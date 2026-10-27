@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-batches.show', $batch) }}" class="text-teal-800 underline">Selected monthly payroll</a>
    <h1 class="mt-5 text-3xl font-bold">Correction requests and payroll versions</h1>
    <p class="mt-3 font-semibold">{{ $batch->period_start->format('Y-m') }} / Selected version {{ $batch->payroll_version }} / {{ ucfirst($batch->status) }}</p>
    <p class="mt-3 rounded bg-amber-50 p-4 font-semibold text-amber-900">Demonstration only. A correction is a replacement payroll version, not an additional payment or a difference to disburse. Earlier finalized values, documents and integrity history remain preserved.</p>
    <p class="mt-3">Authorization opens an empty draft. A Payroll Officer must select coverage and enter reviewed inputs, then calculate, submit and obtain independent approval/finalization using the normal workflow.</p>
    @if ($issue)<p class="mt-4 font-semibold text-amber-900">Integrity review required: {{ $issue }}</p>@endif
    @if (! $batch->is_demonstration)<p class="mt-4">Real payroll corrections remain blocked pending client validation.</p>@endif
    @if ($head->id !== $batch->id)<p class="mt-4">A later version already exists. Use <a class="underline" href="{{ route('payroll-corrections.index', $head) }}">version {{ $head->payroll_version }}</a> for further correction requests.</p>@endif
    @if ($source && $digest && $head->id === $batch->id)
        @can('requestCorrection', $batch)
            <form method="POST" action="{{ route('payroll-corrections.store', [$batch, $source]) }}" class="mt-6 rounded border bg-white p-5">
                @csrf
                <h2 class="text-xl font-semibold">Request correction of finalized version {{ $source->version }}</h2>
                <input type="hidden" name="request_key" value="{{ $requestKey }}"><input type="hidden" name="source_hash" value="{{ $digest }}">
                <label for="correction-reason" class="mt-4 block font-semibold">Correction reason</label><textarea id="correction-reason" name="reason" required maxlength="500" rows="3" class="mt-2 w-full rounded border-slate-300"></textarea>
                <label for="correction-authority" class="mt-4 block font-semibold">Authority reference for this request</label><input id="correction-authority" name="authority_reference" required maxlength="255" class="mt-2 w-full rounded border-slate-300">
                <label class="mt-4 flex gap-3"><input type="checkbox" name="review_confirmed" value="1" required><span>I reviewed the finalized source and request an independently authorized demonstration correction. No existing finalized record will be reopened.</span></label>
                <button class="mt-4 button button-primary">Request correction</button>
            </form>
        @endcan
    @endif
    <h2 class="mt-8 text-xl font-semibold">Preserved version family</h2>
    @foreach ($versions as $version)
        <article class="mt-3 rounded border bg-white p-4">
            <h3 class="font-semibold">Version {{ $version->payroll_version }} / {{ ucfirst($version->status) }} / Batch #{{ $version->id }}</h3>
            <a class="mt-2 inline-block underline" href="{{ route('payroll-batches.show', $version) }}">Open payroll version {{ $version->payroll_version }}</a>
            <a class="mt-2 inline-block underline" href="{{ route('payroll-corrections.index', $version) }}">Version {{ $version->payroll_version }} correction history</a>
            @if ($saved = $finalizations->get($version->id))
                <div class="mt-3 flex flex-wrap gap-4">
                    <a class="underline" href="{{ route('payroll-reviews.show', $version) }}">Preserved finalized review</a>
                    <a class="underline" href="{{ route('payroll-documents.index', $version) }}">Register and payslips</a>
                    <a class="underline" href="{{ route('payroll-anchoring.show', [$version, $saved]) }}">Anchoring history</a>
                    <a class="underline" href="{{ route('payroll-verification.show', [$version, $saved]) }}">Integrity check history</a>
                </div>
            @endif
        </article>
    @endforeach
    <div class="mt-4">{{ $versions->withQueryString()->links() }}</div>
    <h2 class="mt-8 text-xl font-semibold">Requests for selected version</h2>
    @forelse ($requests as $proposal)
        @php($sourceMatches = $digest !== null && hash_equals($proposal->source_hash, $digest))
        <article class="mt-3 rounded border bg-white p-5">
            <h3 class="font-semibold">Request revision {{ $proposal->revision }} / {{ $proposal->decision ? ucfirst($proposal->decision->action) : 'Awaiting independent review' }}</h3>
            <p class="mt-2">Requested by account #{{ $proposal->requested_by }} / {{ $proposal->created_at->format('Y-m-d H:i:s') }}</p>
            <p class="mt-2">{{ $proposal->reason }}</p><p class="mt-2">Authority: {{ $proposal->authority_reference }}</p>
            @if (! $proposal->decision && $digest !== null && ! $sourceMatches)
                <p class="mt-3 font-semibold text-amber-900">Integrity review required: The finalized source changed since this request. Authorization is blocked. An independent Approver can return the request for integrity review.</p>
            @endif
            @if ($decision = $proposal->decision)
                <p class="mt-3 font-semibold">{{ ucfirst($decision->action) }} by account #{{ $decision->created_by }} / {{ $decision->created_at->format('Y-m-d H:i:s') }}</p>
                <p>{{ $decision->reason }}</p><p>Authority: {{ $decision->authority_reference ?? 'Return for correction' }}</p>
                @if ($decision->correction_batch_id)<a class="mt-3 inline-block underline" href="{{ route('payroll-batches.show', $decision->correction_batch_id) }}">Open authorized correction version</a>@endif
            @elseif ($reviewers[$proposal->id] && $head->id === $batch->id)
                @foreach ($sourceMatches ? ['authorize' => 'Authorize correction draft', 'return' => 'Return correction request'] : ['return' => 'Return correction request'] as $action => $label)
                    <form method="POST" action="{{ route('payroll-corrections.decide', [$batch, $source, $proposal, $action]) }}" class="mt-5 rounded border p-4">
                        @csrf
                        <h4 class="font-semibold">{{ $label }}</h4><input type="hidden" name="source_hash" value="{{ $proposal->source_hash }}">
                        <label for="decision-reason-{{ $proposal->id }}-{{ $action }}" class="mt-3 block">Review reason</label><textarea id="decision-reason-{{ $proposal->id }}-{{ $action }}" name="reason" required maxlength="500" class="mt-2 w-full rounded border-slate-300"></textarea>
                        @if ($action === 'authorize')<label for="decision-authority-{{ $proposal->id }}" class="mt-3 block">Authorization reference</label><input id="decision-authority-{{ $proposal->id }}" name="authority_reference" required maxlength="255" class="mt-2 w-full rounded border-slate-300">@endif
                        <label class="mt-3 flex gap-3"><input type="checkbox" name="review_confirmed" value="1" required><span>I independently reviewed this demonstration correction request. This action does not finalize or release payroll.</span></label>
                        <button class="mt-3 button button-secondary">{{ $label }}</button>
                    </form>
                @endforeach
            @endif
        </article>
    @empty<p class="mt-3">No correction requests for this version.</p>@endforelse
    <div class="mt-4">{{ $requests->withQueryString()->links() }}</div>
@endsection
