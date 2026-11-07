<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') | LGU Payroll System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3 focus:text-brand">Skip to content</a>
<div class="flex min-h-dvh flex-col lg:flex-row">
    <details id="app-navigation" open class="w-full shrink-0 bg-brand-strong text-emerald-50 lg:sticky lg:top-0 lg:h-dvh lg:w-64 lg:overflow-y-auto">
        <summary class="min-h-14 cursor-pointer px-5 py-4 text-sm font-semibold lg:hidden">LGU Payroll · Menu</summary>
        <div class="hidden min-h-20 items-center gap-3 border-b border-white/15 px-5 lg:flex">
            <span class="grid h-10 w-10 place-items-center rounded-lg bg-white text-sm font-bold text-brand">LGU</span>
            <div>
                <div class="text-sm font-semibold">Payroll System</div>
                <div class="text-xs text-emerald-200">Local Government Unit</div>
            </div>
        </div>
        <x-sidebar-navigation />
        <div class="border-t border-white/15 px-5 py-4 text-sm">
            <div class="break-words font-semibold">{{ auth()->user()->username }}</div>
            <div class="mt-1 text-emerald-200">{{ auth()->user()->roles->pluck('role_name')->implode(', ') ?: 'No role' }}</div>
        </div>
    </details>
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex min-h-20 items-center justify-between gap-4 border-b border-slate-200 bg-surface px-4 py-4 sm:px-6 lg:px-8">
            <h1 class="min-w-0 break-words text-xl font-semibold tracking-tight sm:text-2xl">@yield('heading', 'Dashboard')</h1>
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
</body>
</html>
