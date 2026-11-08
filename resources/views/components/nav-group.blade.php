@props(['label', 'active' => false])

<details class="ui-nav-group" data-navigation-group="{{ \Illuminate\Support\Str::slug($label) }}" data-current-group="{{ $active ? 'true' : 'false' }}" @if ($active) open @endif>
    <summary>
        <x-nav-icon :name="$label" />
        <span class="min-w-0 flex-1">{{ $label }}</span>
        <svg class="ui-nav-chevron h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m7 4 6 6-6 6" /></svg>
    </summary>
    <div class="ml-5 mt-1 space-y-1 border-l border-white/15 pl-3">{{ $slot }}</div>
</details>
