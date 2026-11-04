@inject('besuStatus', 'App\Services\Blockchain\BesuStatus')
@php
    $chainEnabled = $besuStatus->enabled();
    $chain = $chainEnabled ? $besuStatus->check() : null;
@endphp
@if ($chainEnabled)
    <span @class(['status-badge', 'chain-status', 'is-active' => $chain['online'], 'is-inactive' => ! $chain['online']])
        title="{{ $chain['online']
            ? 'Besu node reachable'.($chain['block_number'] !== null ? ' · block '.number_format($chain['block_number']) : '')
            : 'Besu node unreachable' }}">
        <span class="status-dot" aria-hidden="true"></span>
        <span>{{ $chain['online'] ? 'Blockchain online' : 'Blockchain offline' }}</span>
    </span>
@endif
