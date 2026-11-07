@props(['heading' => null])

<section {{ $attributes->class(['ui-card']) }}>
    @if ($heading)
        <h2 class="mb-4 text-lg font-semibold text-ink">{{ $heading }}</h2>
    @endif
    {{ $slot }}
</section>
