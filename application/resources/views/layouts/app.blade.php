<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LGU Payroll Management System')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-5">
            <a href="{{ url('/') }}" class="text-xl font-bold text-teal-800">LGU Payroll Management System</a>
            @auth
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <span>{{ auth()->user()->name }} · {{ auth()->user()->role->label() }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded border border-slate-300 px-3 py-2 hover:bg-slate-100">Sign out</button>
                    </form>
                </div>
            @endauth
        </div>
        @auth
            <nav aria-label="Main navigation" class="mx-auto flex max-w-6xl flex-wrap gap-5 px-6 pb-4 text-sm font-semibold text-teal-800">
                <a href="{{ route('dashboard') }}">Dashboard</a>
                @foreach (\App\Enums\Role::cases() as $navRole)
                    @can('access-'.$navRole->workspace())
                        <a href="{{ route($navRole->workspace()) }}">{{ $navRole->label() }} workspace</a>
                    @endcan
                @endforeach
                @can('manage-users')
                    <a href="{{ route('users.index') }}">User accounts</a>
                @endcan
                @can('viewAny', \App\Models\Employee::class)
                    <a href="{{ route('employees.index') }}">Employees</a>
                @endcan
                @can('viewAny', \App\Models\SalarySchedule::class)
                    <a href="{{ route('salary-schedules.index') }}">Salary schedules</a>
                @endcan
            </nav>
        @endauth
    </header>
    <main class="mx-auto max-w-6xl px-6 py-10">
        @if (session('status'))
            <p role="status" class="mb-6 rounded border border-teal-200 bg-teal-50 p-4 text-teal-900">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="mb-6 rounded border border-red-200 bg-red-50 p-4 text-red-900">
                <p class="font-semibold">Please check the following:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
</body>
</html>
