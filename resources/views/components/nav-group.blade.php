@props(['label', 'active' => false])

<details class="ui-nav-group" @if ($active) open @endif>
    <summary>{{ $label }}</summary>
    <div class="mt-1 space-y-1">{{ $slot }}</div>
</details>
