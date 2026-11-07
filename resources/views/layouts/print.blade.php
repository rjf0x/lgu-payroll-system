<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Print') | LGU Payroll System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">
    <div class="no-print mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
        <a href="{{ url()->previous() }}" class="text-sm font-semibold text-emerald-700 hover:underline">&larr; Back</a>
        <button onclick="window.print()" class="rounded-md bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Print</button>
    </div>

    <div class="mx-auto my-4 max-w-3xl rounded-xl border border-slate-200 bg-white p-8 shadow-sm print:my-0 print:border-0 print:shadow-none">
        @yield('content')
    </div>
</body>
</html>
