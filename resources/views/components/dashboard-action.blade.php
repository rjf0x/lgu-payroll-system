@props(['href'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex min-h-11 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100']) }}>{{ $slot }}</a>
