@props(['value'])

@php
    $status = strtoupper((string) $value);
    $tone = match ($status) {
        'ACTIVE', 'APPROVED', 'FINALIZED', 'MATCH', 'PUBLISHED', 'ANCHORED' => 'bg-emerald-100 text-emerald-900 ring-emerald-200',
        'PROCESSING' => 'bg-sky-100 text-sky-900 ring-sky-200',
        'PENDING', 'PENDING_APPROVAL', 'RETURNED' => 'bg-amber-100 text-amber-900 ring-amber-200',
        'REJECTED', 'MISMATCH', 'FAILED', 'CANCELLED' => 'bg-rose-100 text-rose-900 ring-rose-200',
        'DRAFT' => 'bg-slate-100 text-slate-700 ring-slate-200',
        default => 'bg-slate-100 text-slate-700 ring-slate-200',
    };
    $label = \Illuminate\Support\Str::headline(strtolower((string) $value));
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset', $tone]) }}>
    {{ $label }}
</span>
