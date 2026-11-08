@props(['label', 'value'])
<div {{ $attributes->class(['ui-card']) }}>
    <dt class="text-sm font-medium text-muted">{{ $label }}</dt>
    <dd class="ui-stat-value mt-3">{{ number_format($value) }}</dd>
</div>
