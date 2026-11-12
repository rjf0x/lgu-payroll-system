@props(['href' => null, 'variant' => 'secondary', 'type' => 'button', 'icon' => null])

@php
    $variantClass = match ($variant) {
        'primary' => 'ui-button-primary',
        'danger' => 'ui-button-danger',
        'warning' => 'ui-button-warning',
        default => 'ui-button-secondary',
    };
    if ($icon) {
        $label = html_entity_decode(trim(strip_tags((string) $slot)), ENT_QUOTES, 'UTF-8');
        $attributes = $attributes->merge(['title' => $label, 'aria-label' => $label, 'data-action-button' => 'true']);
    }
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['ui-button', $variantClass]) }}>
        @if ($icon)
            <x-action-icon :name="$icon" />
        @endif
        <span data-button-label>{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class(['ui-button', $variantClass]) }}>
        @if ($icon)
            <x-action-icon :name="$icon" />
        @endif
        <span data-button-label>{{ $slot }}</span>
    </button>
@endif
