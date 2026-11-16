<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'LGU Payroll System') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-canvas font-sans antialiased">
    <x-page-loader class="mx-auto max-w-6xl px-4 pt-8 sm:px-8" />
    <main data-page-content class="flex min-h-dvh items-center justify-center px-4 py-10 sm:px-8">
        <section class="w-full max-w-2xl overflow-hidden rounded-3xl border border-brand-border bg-white shadow-xl shadow-blue-950/5" aria-labelledby="welcome-heading">
            <div class="flex items-center gap-3 bg-brand-strong px-6 py-6 text-white sm:px-10">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-white/25 bg-white/10 text-sm font-bold">LGU</span>
                <p class="font-semibold">Payroll workspace</p>
            </div>
            <div class="px-6 py-10 sm:p-10">
                <h1 id="welcome-heading" class="break-words text-3xl font-semibold tracking-tight text-brand-ink sm:text-4xl">{{ config('app.name', 'LGU Payroll System') }}</h1>
                <p class="mt-4 max-w-lg text-base leading-7 text-muted">Your workspace for employee records, payroll preparation, independent review, and reports.</p>
                <div class="mt-8">
                    @auth
                        <x-button icon="view" :href="route('dashboard')" variant="primary">Continue to dashboard</x-button>
                    @else
                        <x-button icon="login" :href="route('login')" variant="primary">Sign in to your account</x-button>
                    @endauth
                </div>
            </div>
        </section>
    </main>
</body>
</html>
