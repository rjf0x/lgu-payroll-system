<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') | {{ $systemSettings['system_name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('print-styles')
</head>
<body class="h-full font-sans antialiased">
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3 focus:text-brand">Skip to content</a>
<div class="flex min-h-dvh flex-col lg:flex-row">
    <div id="navigation-backdrop" hidden aria-hidden="true" class="no-print fixed inset-0 z-40 bg-slate-950/50 lg:hidden"></div>
    <details id="app-navigation" open data-navigation-user="{{ auth()->id() }}" class="w-full shrink-0 bg-brand-strong text-blue-50 lg:sticky lg:top-0 lg:z-30 lg:h-dvh lg:w-72">
        <summary class="min-h-14 cursor-pointer px-5 py-4 text-sm font-semibold lg:hidden">Navigation &middot; Menu</summary>
        <div id="navigation-panel" class="flex h-full min-h-0 flex-col" tabindex="-1">
            <div class="flex min-h-20 shrink-0 items-center gap-3 border-b border-white/15 px-5 py-4">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 flex-1 items-center gap-3 rounded-lg">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-sm font-bold text-brand">LGU</span>
                    <div class="min-w-0">
                        <p id="navigation-title" class="break-words text-sm font-semibold">{{ $systemSettings['system_name'] }}</p>
                        <p class="mt-0.5 break-words text-xs text-blue-200">{{ $systemSettings['organization_name'] }}</p>
                    </div>
                </a>
                <button id="navigation-close" type="button" hidden aria-label="Close navigation" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-white hover:bg-white/10 lg:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </div>
            <div id="navigation-search-container" hidden class="shrink-0 px-4 pb-1 pt-3">
                <label for="navigation-search" class="sr-only">Find a section</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-blue-200" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" /><path d="m13 13 4 4" /></svg>
                    <input id="navigation-search" type="search" autocomplete="off" spellcheck="false" placeholder="Search navigation" aria-controls="navigation-links" aria-keyshortcuts="/" class="h-11 w-full min-w-0 rounded-lg border border-blue-300/60 bg-white/5 py-1.5 pl-8 pr-11 text-sm text-white placeholder:text-blue-200 focus:border-white lg:h-9 lg:pr-9">
                    <button id="navigation-search-clear" type="button" hidden aria-label="Clear navigation search" class="absolute right-0 top-0 inline-flex h-11 w-11 items-center justify-center rounded-lg text-white hover:bg-white/10 lg:h-9 lg:w-9">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m6 6 8 8M14 6l-8 8" /></svg>
                    </button>
                </div>
                <p id="navigation-search-status" role="status" aria-live="polite" class="sr-only"></p>
            </div>
            <x-sidebar-navigation />
            <div class="flex shrink-0 items-start gap-3 border-t border-white/15 px-5 py-4 text-sm">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/10 font-semibold text-white" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->username, 0, 1)) }}</span>
                <div class="min-w-0">
                    <p class="break-words font-semibold">{{ auth()->user()->username }}</p>
                    <p class="mt-1 break-words text-xs leading-5 text-blue-200">{{ auth()->user()->roles->pluck('role_name')->implode(', ') ?: 'No role' }}</p>
                    @if ($systemSettings['support_email'])
                        <a href="mailto:{{ $systemSettings['support_email'] }}" class="mt-1 inline-flex min-h-11 items-center text-brand-on-dark underline underline-offset-4">Contact support</a>
                    @endif
                </div>
            </div>
        </div>
    </details>
    <div id="app-workspace" class="flex min-w-0 flex-1 flex-col">
        <header class="app-header sticky top-0 z-20 flex min-h-20 items-center gap-3 border-b border-slate-200 bg-surface px-4 py-4 sm:gap-4 sm:px-6 lg:px-8">
            <button id="navigation-open" type="button" hidden aria-label="Open navigation" aria-controls="navigation-panel" aria-expanded="false" class="ui-button ui-button-secondary no-print px-3 lg:hidden">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                <span>Menu</span>
            </button>
            <h1 class="min-w-0 flex-1 break-words text-lg font-semibold tracking-tight sm:text-2xl">@yield('heading', 'Dashboard')</h1>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button type="submit">Logout</x-button>
            </form>
        </header>
        <main id="main-content" tabindex="-1" class="mx-auto w-full min-w-0 max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <x-breadcrumbs />
            @if (session('status'))
                <x-alert class="mb-5">{{ session('status') }}</x-alert>
            @endif
            @if (session('error'))
                <x-alert variant="error" class="mb-5">{{ session('error') }}</x-alert>
            @endif
            <x-form.error-summary />
            <p id="submission-status" role="status" class="sr-only"></p>
            @yield('content')
        </main>
    </div>
</div>
<x-confirmation-dialog />
</body>
</html>
