@extends('layouts.app')
@section('title', 'Anchor record · LGU Payroll')
@section('content')
    @php
        $qs = \App\Services\PayrollAnchorQueryService::class;
        $format = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i:s') : 'Not available';
    @endphp
    <a href="{{ route('besu-operations.index') }}" class="text-teal-800 underline">Blockchain operations</a>
    <h1 class="mt-5 text-3xl font-bold">Payroll hash anchor #{{ $anchor->id }}</h1>
    <p class="mt-3">Reference metadata and digests only. Confidential payroll values and signed transaction bytes are never displayed.</p>

    <dl class="monitor-ledger mt-5">
        <div><dt>Payroll reference</dt><dd>{{ $anchor->period_start }} · Batch #{{ $anchor->batch_id }} · version {{ $anchor->payroll_version }}</dd></div>
        <div><dt>Certification reference</dt><dd>Finalization #{{ $anchor->finalization_id }} (version {{ $anchor->finalization_version }})</dd></div>
        <div><dt>Hash algorithm</dt><dd>{{ $anchor->algorithm }}</dd></div>
        <div><dt>Canonicalization</dt><dd>{{ $anchor->canonicalization }}</dd></div>
        <div><dt>Payroll hash</dt><dd class="mono">{{ $anchor->sha256 }}</dd></div>
        <div><dt>Anchor status</dt><dd>{{ $qs::anchorLabel($anchor->anchor_status) }}</dd></div>
        <div><dt>Created at</dt><dd>{{ $format($anchor->created_at) }}</dd></div>
        <div><dt>Anchored at</dt><dd>{{ $format($anchor->confirmed_at) }}</dd></div>
    </dl>

    <section class="mt-5 rounded border bg-white" aria-label="Anchoring journal detail">
        <h2>Journal</h2>
        <dl class="monitor-ledger">
            <div><dt>Reference</dt><dd class="mono">{{ $anchor->reference ?? 'Not queued' }}</dd></div>
            <div><dt>Transaction hash</dt><dd class="mono">{{ $anchor->transaction_hash ?? 'None' }}</dd></div>
            <div><dt>Block</dt><dd>{{ $anchor->block_number !== null ? '#'.$anchor->block_number : 'None' }}</dd></div>
            <div><dt>Last check</dt><dd>{{ $anchor->last_error ? str_replace('_', ' ', $anchor->last_error) : 'No recorded error' }}</dd></div>
            <div><dt>Consecutive incomplete checks</dt><dd>{{ (int) $anchor->retry_count }}</dd></div>
            <div><dt>Next eligible check</dt><dd>{{ $format($anchor->next_attempt_at) }}</dd></div>
        </dl>
    </section>

    <h2 class="mt-8 text-xl font-semibold">Recorded submission attempts</h2>
    @forelse ($attempts as $attempt)
        <article class="mt-3 rounded border bg-white">
            <h3 class="font-semibold">Attempt #{{ $attempt->id }} · {{ $qs::submissionLabel($attempt->status) }}</h3>
            <p class="mt-2">{{ $qs::submissionResult($attempt->status) }}</p>
            <p class="mt-2">Attempted {{ $format($attempt->created_at) }} · completed {{ $format($attempt->completed_at) }}</p>
            @if ($attempt->network || $attempt->transaction_reference)<p class="mt-2 break-all mono">{{ $attempt->network }} {{ $attempt->transaction_reference }}</p>@endif
        </article>
    @empty<p class="mt-3">No submission attempts were recorded for this anchor.</p>@endforelse

    <h2 class="mt-8 text-xl font-semibold">Retained transaction journal</h2>
    @forelse ($transactions as $transaction)
        <article class="mt-3 rounded border bg-white">
            <h3 class="font-semibold mono">{{ $transaction->transaction_hash }}</h3>
            <p class="mt-2">Status: {{ $transaction->status }} · nonce: {{ $transaction->nonce ?? 'None' }}
                @if ($transaction->block_number !== null) · block #{{ $transaction->block_number }}@endif</p>
            <p class="mt-2">Created {{ $format($transaction->created_at) }} · confirmed {{ $format($transaction->confirmed_at) }}</p>
        </article>
    @empty<p class="mt-3">No transaction history is retained for this anchor.</p>@endforelse

    <h2 class="mt-8 text-xl font-semibold">Journal events</h2>
    <ul class="mt-3">
        @forelse ($events as $event)
            <li>{{ str_replace('_', ' ', $event->event) }} · {{ $format($event->created_at) }}</li>
        @empty
            <li>No journal events were recorded.</li>
        @endforelse
    </ul>
@endsection
