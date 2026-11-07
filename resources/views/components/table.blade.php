@props(['label', 'stickyFirst' => false])

<div role="region" aria-label="{{ $label }}" tabindex="0" {{ $attributes->class(['ui-table-scroll']) }}>
    <table @class(['ui-table', 'ui-table-sticky-first' => $stickyFirst])>
        <caption class="sr-only">{{ $label }}</caption>
        {{ $slot }}
    </table>
</div>
