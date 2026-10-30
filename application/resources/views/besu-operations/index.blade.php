@extends('layouts.app')
@section('content')
    <h1 class="text-3xl font-bold">Blockchain operations</h1>
    <p class="mt-3">Recorded submission status. This page does not contact the network or authorize payroll.</p>
    <dl class="mt-5 rounded border bg-white p-4">
        @foreach ($counts as $status => $total)<dt class="font-semibold">{{ ucfirst($status) }}</dt><dd>{{ $total }}</dd>@endforeach
    </dl>
    @forelse ($anchors as $anchor)
        <article class="mt-4 rounded border bg-white p-4">
            <h2 class="text-xl font-semibold">Outbox #{{ $anchor->id }}: {{ ucfirst($anchor->status) }}</h2>
            @if ($anchor->transaction_hash)<p class="mt-2 break-all font-mono">Transaction: {{ $anchor->transaction_hash }}</p>@endif
            @if ($anchor->block_hash)<p class="mt-2 break-all">Block {{ $anchor->block_number }}: {{ $anchor->block_hash }}</p>@endif
            @if ($anchor->last_error)<p class="mt-2">Last check: {{ str_replace('_', ' ', $anchor->last_error) }}. Incomplete checks do not establish confirmation.</p>@endif
            @if ($anchor->next_attempt_at)<p class="mt-2">Next eligible check: {{ $anchor->next_attempt_at->format('Y-m-d H:i:s') }}</p>@endif
            <p class="mt-2">Consecutive incomplete checks: {{ $anchor->retry_count }}. Updated {{ $anchor->updated_at->format('Y-m-d H:i:s') }}.</p>
        </article>
    @empty<p class="mt-4">No blockchain submissions are queued.</p>@endforelse
    <div class="mt-4">{{ $anchors->links() }}</div>
@endsection
