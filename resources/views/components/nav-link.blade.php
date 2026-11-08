@props(['href', 'active' => false, 'icon' => null])

<a
    href="{{ $href }}"
    data-navigation-link
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge([
        'class' => 'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm '
            .($active ? 'bg-brand font-semibold text-white shadow-sm' : 'text-brand-on-dark hover:bg-white/10 hover:text-white'),
    ]) }}
>
    @if ($icon)<x-nav-icon :name="$icon" />@endif
    <span class="min-w-0 flex-1">{{ $slot }}</span>
    @if ($active)<span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-200" aria-hidden="true"></span>@endif
</a>
