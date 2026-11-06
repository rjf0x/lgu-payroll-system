@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'block rounded-md px-3 py-2 transition '
            .($active ? 'bg-emerald-700 font-semibold text-white' : 'text-emerald-100 hover:bg-emerald-800'),
    ]) }}
>
    {{ $slot }}
</a>
