@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-reviews.show', $batch) }}" class="text-teal-800 underline">Payroll review and finalized snapshot</a>
    <h1 class="mt-5 text-3xl font-bold">Blockchain anchoring</h1>
    <p class="mt-3 font-semibold">{{ $batch->period_start->format('Y-m') }} / Batch #{{ $batch->id }} / Finalized version {{ $finalization->version }}</p>
    <p class="mt-3 rounded bg-amber-50 p-4 font-semibold text-amber-900">Local interface only. No authorized blockchain test network is available. This payroll has not been confirmed on a blockchain. Demonstration only - not for real payroll.</p>
    <p class="mt-3">Payroll status: {{ ucfirst($batch->status) }}. A local SHA-256 hash is not a successful blockchain anchor or an integrity verification.</p>
    <dl class="mt-5 rounded border bg-white p-4">
        <dt class="font-semibold">Current finalized snapshot SHA-256</dt><dd class="mt-2 break-all font-mono">{{ $digest }}</dd>
        <dt class="mt-4 font-semibold">Saved local commitment</dt><dd class="mt-2 break-all font-mono">{{ $commitment?->sha256 ?? 'Not prepared' }}</dd>
        <dt class="mt-4 font-semibold">Blockchain submission</dt><dd class="mt-2">{{ $latest ? ucfirst($latest->status) : 'Not submitted' }}</dd>
    </dl>
    @if (! $matches)<p class="mt-4 font-semibold text-amber-900">The finalized snapshot, preserved bytes or local commitment differ. Submission is blocked; preserve the records for investigation.</p>@endif
    @if ($latest && in_array($latest->status, ['submitting', 'pending', 'unknown'], true))
        <p class="mt-4">Resubmission is blocked while a transaction is pending or acceptance is uncertain. Network reconciliation and confirmation are unavailable in this local-only phase.</p>
    @elseif ($canAct)
        <form method="POST" action="{{ route('payroll-anchoring.act', [$batch, $finalization, $commitment ? 'submit' : 'prepare']) }}" class="mt-6 rounded border bg-white p-6">
            @csrf
            <input type="hidden" name="sha256" value="{{ $digest }}">
            <label class="flex items-start gap-3"><input type="checkbox" name="demonstration_confirmed" value="1" required><span>I reviewed this finalized demonstration version. Only its hash may be submitted; confidential payroll details must stay off-chain.</span></label>
            <button class="mt-4 button button-primary">{{ $commitment ? 'Attempt submission through configured interface' : 'Prepare local commitment' }}</button>
            @if ($commitment)<p class="mt-3">The current interface records an unavailable attempt without sending a transaction. A real network adapter is still required.</p>@endif
        </form>
    @endif
    <h2 class="mt-8 text-xl font-semibold">Submission attempts</h2>
    @forelse ($attempts as $attempt)
        <article class="mt-3 rounded border bg-white p-4">
            <h3 class="font-semibold">{{ ucfirst($attempt->status) }} / {{ $attempt->created_at->format('Y-m-d H:i:s') }}</h3>
            @if ($attempt->status === 'unavailable')<p>No network submission occurred. No transaction reference exists.</p>@endif
            @if ($attempt->status === 'failed')<p>The submission interface reported rejection before acceptance. No successful anchor exists.</p>@endif
            @if ($attempt->status === 'pending')<p>A transaction reference was returned. Confirmation has not been established.</p>@endif
            @if (in_array($attempt->status, ['unknown', 'submitting'], true))<p>Acceptance is uncertain. Do not resubmit until network reconciliation is available.</p>@endif
            @if ($attempt->transaction_reference)<p class="mt-2 break-all">Network: {{ $attempt->network }} / Transaction: {{ $attempt->transaction_reference }}</p>@endif
        </article>
    @empty<p class="mt-3">No submission attempts. Preparing a local hash does not contact a blockchain.</p>@endforelse
    <div class="mt-4">{{ $attempts->links() }}</div>
@endsection
