@props(['href', 'icon'])
<a href="{{ $href }}" {{ $attributes->class(['group flex h-full min-h-24 min-w-0 flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-surface px-4 py-3 text-center text-sm font-semibold text-brand shadow-sm hover:border-brand hover:bg-brand-soft']) }}>
    <span class="ui-icon-tile group-hover:bg-brand group-hover:text-white">
        <x-nav-icon :name="$icon" />
    </span>
    <span class="break-words">{{ $slot }}</span>
</a>
