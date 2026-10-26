@props(['href', 'active' => false, 'icon' => 'grid'])
<a href="{{ $href }}" @class(['nav-link', 'is-active' => $active]) @if ($active) aria-current="page" @endif>
    <x-ui.icon :name="$icon" /><span>{{ $slot }}</span>
    @if ($active)<span class="nav-active-dot" aria-hidden="true"></span>@endif
</a>
