@props(['href'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex min-h-11 items-center rounded-lg border border-brand-border bg-brand-soft px-4 py-2.5 text-sm font-semibold text-brand hover:bg-blue-100']) }}>{{ $slot }}</a>
