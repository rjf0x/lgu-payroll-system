@props(['label', 'stickyFirst' => false, 'compact' => false])

<div role="region" aria-label="{{ $label }}" tabindex="0" {{ $attributes->class(['ui-table-scroll']) }}>
    <table @class(['ui-table', 'ui-table-sticky-first' => $stickyFirst, 'ui-table-compact' => $compact])>
        <caption class="sr-only">{{ $label }}</caption>
        {{ $slot }}
    </table>
</div>
