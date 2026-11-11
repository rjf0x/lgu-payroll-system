@props(['href', 'icon'])
<a href="{{ $href }}" {{ $attributes->class(['group flex h-full min-h-24 min-w-0 flex-col items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-4 text-center text-sm font-semibold text-brand-ink shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-sky-200 hover:bg-sky-50 hover:shadow-md']) }}>
    <span class="ui-icon-tile group-hover:bg-brand group-hover:text-white">
        <x-nav-icon :name="$icon" />
    </span>
    <span class="break-words">{{ $slot }}</span>
</a>
