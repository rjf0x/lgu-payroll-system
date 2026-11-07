@props(['description' => null])

<div {{ $attributes->class(['mb-6 flex flex-wrap items-start justify-between gap-4']) }}>
    @if ($description)
        <p class="max-w-2xl text-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-3">{{ $slot }}</div>
    @endif
</div>
