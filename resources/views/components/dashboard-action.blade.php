@props(['href', 'icon'])
<a href="{{ $href }}" {{ $attributes->class(['ui-button ui-button-secondary w-full']) }}>
    <x-nav-icon :name="$icon" />
    <span>{{ $slot }}</span>
</a>
