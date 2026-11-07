@props(['href' => null, 'variant' => 'secondary', 'type' => 'button'])

@php
    $variantClass = match ($variant) {
        'primary' => 'ui-button-primary',
        'danger' => 'ui-button-danger',
        'warning' => 'ui-button-warning',
        default => 'ui-button-secondary',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['ui-button', $variantClass]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class(['ui-button', $variantClass]) }}>{{ $slot }}</button>
@endif
