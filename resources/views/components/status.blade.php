@props(['value'])

@php
    $active = strtoupper((string) $value) === 'ACTIVE';
@endphp

<span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
    {{ $value }}
</span>
