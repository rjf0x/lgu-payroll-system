@inject('monitoring', 'App\Services\BlockchainMonitoringService')
@php
    $summary = $monitoring->summary();
    $state = $summary['state'];
    $stateClass = match ($state) {
        'integrity_issue', 'service_unavailable' => 'is-issue',
        'verification_required' => 'is-inactive',
        default => 'is-active',
    };
    $format = function ($value) {
        if (! $value || ! (is_string($value) || $value instanceof \DateTimeInterface)) {
            return 'Not available';
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return 'Not available';
        }
    };
@endphp
<details class="chain-status">
    <summary class="status-badge {{ $stateClass }}" aria-label="Blockchain status: {{ $summary['label'] }}">
        <span class="status-dot" aria-hidden="true"></span>
        <span>{{ $summary['label'] }}</span>
    </summary>
    <div class="chain-status-menu" role="group" aria-label="Blockchain status detail">
        <p class="chain-status-title">{{ $summary['label'] }}</p>
        <dl>
            <div><dt>Ledger availability</dt><dd>{{ $summary['available'] ? 'Available' : 'Unavailable' }}</dd></div>
            <div><dt>Ledger integrity</dt><dd>{{ $summary['verification']['id'] ? ($summary['verification']['status'] === 'verified' ? 'Verified' : 'Issues found') : 'Not yet verified' }}</dd></div>
            <div><dt>Latest verification</dt><dd>{{ $format($summary['verification']['created_at']) }}</dd></div>
            <div><dt>External blockchain</dt><dd>{{ $summary['chain']['label'] }}@if ($summary['chain']['block_number'] !== null) · block {{ number_format($summary['chain']['block_number']) }}@endif</dd></div>
            <div><dt>Latest successful anchor</dt><dd>{{ $format($summary['anchors']['latest_confirmed_at']) }}</dd></div>
        </dl>
        @if ($summary['verification']['stale'])
            <p class="chain-status-note">The ledger changed after the last verification. A fresh check is recommended.</p>
        @endif
        @unless ($summary['chain']['enabled'])
            <p class="chain-status-note">External anchoring is not configured. Ledger integrity is monitored locally.</p>
        @endunless
        @can('manage-blockchain')
            <a href="{{ route('besu-operations.index') }}" class="chain-status-link">Open Blockchain operations</a>
        @endcan
    </div>
</details>
