<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') | LGU Payroll System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-full bg-slate-100 font-sans text-slate-800 antialiased">
<a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3">Skip to content</a>
<div class="flex min-h-full">

    <aside class="hidden w-60 shrink-0 flex-col bg-emerald-900 text-emerald-50 md:flex">
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-white text-sm font-bold text-emerald-800">LGU</span>
            <div>
                <div class="text-sm font-semibold">Payroll System</div>
                <div class="text-[10px] text-emerald-200">Local Government Unit</div>
            </div>
        </div>

        <x-sidebar-navigation />

        <div class="border-t border-white/10 px-5 py-4 text-xs">
            <div class="font-semibold">{{ auth()->user()->username }}</div>
            <div class="text-emerald-200">{{ auth()->user()->roles->pluck('role_name')->implode(', ') ?: 'No role' }}</div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <details class="bg-emerald-900 text-emerald-50 md:hidden">
            <summary class="cursor-pointer px-6 py-4 text-sm font-semibold">LGU Payroll � Navigation</summary>
            <x-sidebar-navigation />
        </details>
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-6">
            <h1 class="text-lg font-semibold text-slate-800">@yield('heading', 'Dashboard')</h1>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    Logout
                </button>
            </form>
        </header>

        <main id="main-content" class="mx-auto w-full max-w-6xl flex-1 px-6 py-7">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

</div>
</body>
</html>
