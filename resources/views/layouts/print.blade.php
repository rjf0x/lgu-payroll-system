<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Print') | {{ $systemSettings['system_name'] }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="bg-slate-100 font-sans text-slate-800 antialiased">
    <div class="no-print mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
        <x-button :href="route('payroll-runs.payslips', $payslip->payrollDetail->payrollRun)">Back to payslips</x-button>
        <x-button onclick="window.print()" variant="primary">Print</x-button>
    </div>

    <main class="print-document mx-auto my-4 min-w-0 max-w-3xl rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-8">
        <p class="mb-3 break-words text-center text-lg font-semibold">{{ $systemSettings['organization_name'] }}</p>
        @yield('content')
    </main>
</body>
</html>
