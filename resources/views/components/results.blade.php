@props(['items'])

<p {{ $attributes->class(['mb-3 text-sm text-muted']) }}>
    @if ($items->total())
        Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }} records.
    @else
        No matching records. Try changing or resetting the filters.
    @endif
</p>
