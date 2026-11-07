@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge([
        'class' => 'flex min-h-11 items-center rounded-lg px-3 py-2.5 text-sm '
            .($active ? 'bg-brand font-semibold text-white' : 'text-brand-on-dark hover:bg-blue-900'),
    ]) }}
>
    {{ $slot }}
</a>
