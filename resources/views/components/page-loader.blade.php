@php
    $currentKind = match (true) {
        request()->routeIs('dashboard') => 'dashboard',
        request()->routeIs('login', 'login.store') || request()->is('/') => 'auth',
        request()->routeIs('*.create', '*.edit', 'system-settings.*') => 'form',
        request()->routeIs('reports.*', '*.register', 'payslips.show') => 'report',
        request()->routeIs('*.index', '*.payslips') => 'table',
        default => 'detail',
    };
@endphp

<section data-page-loader data-current-kind="{{ $currentKind }}" data-dashboard-url="{{ route('dashboard') }}" hidden {{ $attributes->class(['no-print min-w-0 py-2']) }} aria-label="Page loading">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p data-page-loading-status role="status" aria-live="polite" class="text-sm font-medium text-muted"></p>
        <x-button icon="back" data-page-loading-dismiss>Show current page</x-button>
    </div>
    <div data-page-loading-preview></div>
    @foreach (['dashboard', 'table', 'form', 'detail', 'report', 'auth'] as $kind)
        <template data-loading-template="{{ $kind }}">
            <div aria-hidden="true" class="space-y-5 motion-safe:animate-pulse">
                @if ($kind === 'auth')
                    <div class="mx-auto max-w-lg rounded-2xl border border-slate-200 bg-surface p-6 sm:p-10">
                        <div class="ui-skeleton mb-6 h-12 w-12 rounded-xl"></div>
                        <div class="ui-skeleton mb-3 h-7 w-2/3"></div>
                        <div class="ui-skeleton mb-8 h-4 w-full"></div>
                        @for ($field = 0; $field < 2; $field++)
                            <div class="ui-skeleton mb-2 h-3 w-24"></div>
                            <div class="ui-skeleton mb-6 h-11 w-full"></div>
                        @endfor
                        <div class="ui-skeleton h-11 w-full"></div>
                    </div>
                @else
                    <div class="flex items-center justify-between gap-4">
                        <div class="space-y-3">
                            <div class="ui-skeleton h-6 w-40 sm:w-64"></div>
                            <div class="ui-skeleton h-3 w-32 sm:w-48"></div>
                        </div>
                        <div class="ui-skeleton h-10 w-24"></div>
                    </div>
                    @if (in_array($kind, ['dashboard', 'report'], true))
                        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                            @for ($card = 0; $card < 4; $card++)
                                <div class="rounded-2xl border border-slate-200 bg-surface p-5">
                                    <div class="ui-skeleton mb-4 h-3 w-3/4"></div>
                                    <div class="ui-skeleton h-8 w-1/2"></div>
                                </div>
                            @endfor
                        </div>
                    @endif
                    @if (in_array($kind, ['form', 'detail', 'dashboard'], true))
                        <div class="rounded-2xl border border-slate-200 bg-surface p-5 sm:p-6">
                            <div class="ui-skeleton mb-6 h-5 w-36"></div>
                            <div class="grid gap-6 sm:grid-cols-2 {{ $kind === 'dashboard' ? 'lg:grid-cols-3' : '' }}">
                                @for ($field = 0; $field < 6; $field++)
                                    <div class="space-y-3">
                                        <div class="ui-skeleton h-3 w-24"></div>
                                        <div class="ui-skeleton {{ $kind === 'form' ? 'h-10 w-full' : 'h-5 w-3/4' }}"></div>
                                    </div>
                                @endfor
                            </div>
                            @if ($kind === 'form')
                                <div class="mt-8 flex gap-3">
                                    <div class="ui-skeleton h-10 w-28"></div>
                                    <div class="ui-skeleton h-10 w-20"></div>
                                </div>
                            @endif
                        </div>
                    @endif
                    @if ($kind !== 'form')
                        @if ($kind === 'table')
                            <div class="flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-surface p-4">
                                <div class="ui-skeleton h-10 w-full sm:w-64"></div>
                                <div class="ui-skeleton h-10 w-32"></div>
                                <div class="ui-skeleton h-10 w-20"></div>
                            </div>
                        @endif
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-surface">
                            @for ($row = 0; $row < 7; $row++)
                                <div class="grid grid-cols-3 gap-5 border-b border-slate-100 p-4 last:border-0 sm:grid-cols-4 {{ $row === 0 ? 'bg-slate-50' : '' }}">
                                    <div class="ui-skeleton h-4 w-3/4"></div>
                                    <div class="ui-skeleton h-4 w-2/3"></div>
                                    <div class="ui-skeleton h-4 w-1/2"></div>
                                    <div class="ui-skeleton hidden h-4 w-2/3 sm:block"></div>
                                </div>
                            @endfor
                        </div>
                    @endif
                @endif
            </div>
        </template>
    @endforeach
</section>
