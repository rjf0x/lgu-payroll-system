@props([
    'variant' => 'primary',
    'appearance' => 'light',
    'size' => 'md',
    'title' => null,
    'message' => null,
    'dismissible' => true,
    'autoDismiss' => null,
    'duration' => null,
    'icon' => null,
    'toast' => false,
])

@php
    $variants = [
        'success' => ['label' => 'Success', 'icon' => 'circle-check', 'duration' => 5000],
        'destructive' => ['label' => 'Action unsuccessful', 'icon' => 'circle-x', 'duration' => 0],
        'warning' => ['label' => 'Warning', 'icon' => 'triangle-alert', 'duration' => 0],
        'info' => ['label' => 'Information', 'icon' => 'info', 'duration' => 6000],
        'primary' => ['label' => 'Notification', 'icon' => 'bell', 'duration' => 0],
        'secondary' => ['label' => 'Notification', 'icon' => 'bell', 'duration' => 0],
        'mono' => ['label' => 'Notification', 'icon' => 'bell', 'duration' => 0],
    ];
    $variant = $variant === 'error' ? 'destructive' : $variant;
    $variant = array_key_exists($variant, $variants) ? $variant : 'secondary';
    $appearance = in_array($appearance, ['solid', 'outline', 'light'], true) ? $appearance : 'light';
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $settings = $variants[$variant];
    $title = $title ?? $settings['label'];
    $icon = $icon ?? $settings['icon'];
    $duration = max(0, (int) ($duration ?? ($settings['duration'] ?: 5000)));
    $options = [
        'autoDismiss' => $autoDismiss ?? ($settings['duration'] > 0),
        'duration' => $duration,
        'toast' => (bool) $toast,
    ];
    $variantClass = match ($variant) {
        'success' => 'ui-alert-success',
        'destructive' => 'ui-alert-destructive',
        'warning' => 'ui-alert-warning',
        'info' => 'ui-alert-info',
        'primary' => 'ui-alert-primary',
        'mono' => 'ui-alert-mono',
        default => 'ui-alert-secondary',
    };
    $appearanceClass = match ($appearance) {
        'solid' => 'ui-alert-solid',
        'outline' => 'ui-alert-outline',
        default => 'ui-alert-light',
    };
    $sizeClass = match ($size) {
        'sm' => 'ui-alert-sm',
        'lg' => 'ui-alert-lg',
        default => 'ui-alert-md',
    };
@endphp

<div
    data-alert
    data-alert-variant="{{ $variant }}"
    data-alert-appearance="{{ $appearance }}"
    data-alert-size="{{ $size }}"
    x-data="payrollAlert({{ Illuminate\Support\Js::from($options) }})"
    x-show="visible"
    x-bind:inert="!visible"
    x-bind:aria-hidden="!visible"
    x-on:mouseenter="hovered = true; schedule()"
    x-on:mouseleave="hovered = false; schedule()"
    x-on:focusin="focused = true; schedule()"
    x-on:focusout="focused = $el.contains($event.relatedTarget); schedule()"
    x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none motion-reduce:duration-0"
    x-transition:enter-start="opacity-0 motion-safe:-translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none motion-reduce:duration-0"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    {{ $attributes->class(['ui-alert', $variantClass, $appearanceClass, $sizeClass])->merge([
        'role' => in_array($variant, ['destructive', 'warning'], true) ? 'alert' : 'status',
        'aria-atomic' => 'true',
    ]) }}
>
    @if ($icon !== false)
        <x-action-icon :name="$icon" :size="$size" class="ui-alert-icon" />
    @endif
    <div class="min-w-0 flex-1 space-y-1 [overflow-wrap:anywhere]">
        @if ($title !== '')
            <p class="font-semibold leading-snug">{{ $title }}</p>
        @endif
        @if ($slot->isNotEmpty())
            <div>{{ $slot }}</div>
        @elseif ($message !== null)
            <p>{{ $message }}</p>
        @endif
    </div>
    @if ($dismissible)
        <button type="button" data-alert-dismiss x-cloak x-on:click="dismiss()" aria-label="Dismiss notification" class="ui-alert-dismiss">
            <x-action-icon name="close" size="sm" />
        </button>
    @endif
</div>
