@props(['variant' => 'success'])

<div role="{{ $variant === 'error' ? 'alert' : 'status' }}" {{ $attributes->class([
    'rounded-lg border px-4 py-3 text-sm',
    'border-rose-300 bg-rose-50 text-rose-900' => $variant === 'error',
    'border-emerald-300 bg-emerald-50 text-emerald-900' => $variant !== 'error',
]) }}>{{ $slot }}</div>
