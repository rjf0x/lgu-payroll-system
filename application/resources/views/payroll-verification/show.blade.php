@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-reviews.show', $batch) }}" class="text-teal-800 underline">Payroll review and finalized snapshot</a>
    <h1 class="mt-5 text-3xl font-bold">Payroll integrity checks and history</h1>
    <p class="mt-3 font-semibold">{{ $batch->period_start->format('Y-m') }} / Batch #{{ $batch->id }} / Finalized version {{ $finalization->version }}</p>
    <p class="mt-3 font-semibold text-amber-900">Demonstration only - not for real payroll. An integrity check does not authorize payroll or release payment.</p>
    @unless ($readerConfigured)<p class="mt-3 rounded bg-amber-50 p-4 font-semibold">No authorized blockchain reader is configured. Local consistency is not blockchain verification.</p>@endunless
    <dl class="mt-5 rounded border bg-white p-4">
        <dt class="font-semibold">Current local check</dt><dd class="mt-2">{{ \App\Services\PayrollVerificationService::MESSAGES[$local['reason_code']] }}</dd>
        <dt class="mt-4 font-semibold">Current snapshot SHA-256</dt><dd class="mt-2 break-all font-mono">{{ $local['current_hash'] ?? 'Cannot be serialized' }}</dd>
        <dt class="mt-4 font-semibold">Saved local commitment SHA-256</dt><dd class="mt-2 break-all font-mono">{{ $local['saved_hash'] ?? 'Not prepared' }}</dd>
    </dl>
    @if ($stale)<p class="mt-4 font-semibold text-amber-900">The most recent saved check does not describe the current local records. A fresh check is required.</p>@endif
    <p class="mt-4">Saved results describe the recorded check time. They are not continuous confirmation of network state.</p>
    @can('verifyIntegrity', $batch)
        <form method="POST" action="{{ route('payroll-verification.store', [$batch, $finalization]) }}" class="mt-5">
            @csrf
            <input type="hidden" name="request_key" value="{{ $requestKey }}">
            <button class="button button-primary">Run integrity check</button>
        </form>
    @endcan
    <a href="{{ route('payroll-anchoring.show', [$batch, $finalization]) }}" class="mt-4 inline-block text-teal-800 underline">Anchoring requests and transaction attempts</a>
    <h2 class="mt-8 text-xl font-semibold">Retained check history</h2>
    @forelse ($checks as $check)
        <article class="mt-3 rounded border bg-white p-4">
            <h3 class="font-semibold">{{ ucfirst(str_replace('_', ' ', $check->status)) }} / {{ $check->created_at->format('Y-m-d H:i:s') }}</h3>
            <p class="mt-2">{{ \App\Services\PayrollVerificationService::MESSAGES[$check->reason_code] ?? 'Unsupported saved result; review required.' }}</p>
            <p class="mt-2">Checked by account #{{ $check->created_by }}. Local result: {{ $check->local_status }}.</p>
            <p class="mt-2 break-all font-mono">Snapshot: {{ $check->current_hash ?? 'Unavailable' }} / Saved: {{ $check->saved_hash ?? 'Not prepared' }}</p>
            @if ($check->transaction_reference)<p class="mt-2 break-all">Network: {{ $check->network }} / Transaction: {{ $check->transaction_reference }}</p>@endif
            @if ($check->block_reference)<p class="mt-2 break-all">Block: {{ $check->block_reference }} / Confirmations: {{ $check->confirmations }} / Network SHA-256: {{ $check->network_hash }}</p>@endif
        </article>
    @empty<p class="mt-3">No integrity checks have been saved.</p>@endforelse
    <div class="mt-4">{{ $checks->links() }}</div>
@endsection
