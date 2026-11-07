@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge([
        'class' => 'flex min-h-11 items-center rounded-lg px-3 py-2.5 text-sm '
            .($active ? 'bg-emerald-700 font-semibold text-white' : 'text-emerald-100 hover:bg-emerald-800'),
    ]) }}
>
    {{ $slot }}
</a>
