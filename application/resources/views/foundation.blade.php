<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-12">
            <section class="w-full rounded-2xl border border-slate-200 bg-white p-8 shadow-sm sm:p-12" aria-labelledby="page-title">
                <p class="text-sm font-semibold tracking-widest text-teal-700 uppercase">Accounting Office</p>
                <h1 id="page-title" class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">LGU Payroll Management System</h1>
                <p class="mt-5 text-lg leading-8 text-slate-600">A foundation for accurate payroll preparation and integrity verification.</p>
                <div class="mt-8 rounded-xl border border-teal-200 bg-teal-50 p-5">
                    <h2 class="font-semibold text-teal-900">Phase 0 · Project Foundation</h2>
                    <p class="mt-2 leading-7 text-teal-900">The application foundation is running. Payroll processing and user access will be introduced in later phases.</p>
                </div>
                <p class="mt-8 text-sm leading-6 text-slate-500">Development prototype. No employee or payroll records are displayed here.</p>
            </section>
        </main>
    </body>
</html>
