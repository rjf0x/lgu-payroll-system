@props(['label', 'value'])
<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm']) }}>
    <dt class="text-sm text-slate-500">{{ $label }}</dt>
    <dd class="mt-3 text-3xl font-semibold tracking-tight text-emerald-900">{{ number_format($value) }}</dd>
</div>
