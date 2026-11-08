@props(['heading' => null])

<section {{ $attributes->class(['ui-card']) }}>
    @if ($heading)
        <h2 class="ui-section-heading mb-4">{{ $heading }}</h2>
    @endif
    {{ $slot }}
</section>
