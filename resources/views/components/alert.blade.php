@props(['variant' => 'success'])

<div role="{{ $variant === 'error' ? 'alert' : 'status' }}" {{ $attributes->class([
    'rounded-lg border px-4 py-3 text-sm',
    'border-rose-300 bg-rose-50 text-rose-900' => $variant === 'error',
    'border-amber-300 bg-amber-50 text-amber-900' => $variant === 'warning',
    'border-emerald-300 bg-emerald-50 text-emerald-900' => ! in_array($variant, ['error', 'warning'], true),
]) }}>{{ $slot }}</div>
