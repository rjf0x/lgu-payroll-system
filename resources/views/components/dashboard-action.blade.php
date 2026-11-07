@props(['href'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700']) }}>{{ $slot }}</a>
