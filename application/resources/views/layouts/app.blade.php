<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>@yield('title', 'LGU Payroll Management System')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ auth()->check() ? 'workspace-body' : 'auth-body' }}">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    @auth
        @php
            $sectionTitle = match (true) {
                request()->routeIs('users.*') => 'User accounts',
                request()->routeIs('payroll-rule-versions.*') => 'Payroll configuration',
                request()->routeIs('salary-schedules.*', 'salary-rates.*') => 'Salary schedules',
                request()->routeIs('employees.*', 'employee-salaries.*') => 'Employees',
                request()->routeIs('payroll-batches.*', 'payroll-employees.*', 'payroll-inputs.*', 'payroll-calculations.*') => 'Monthly payroll drafts',
                request()->routeIs('dashboard') => 'Overview',
                default => 'Your workspace',
            };
        @endphp
        <header class="app-topbar">
            <div class="topbar-title"><button type="button" class="navigation-toggle" data-navigation-toggle aria-controls="primary-navigation" aria-expanded="true" aria-label="Toggle navigation"><x-ui.icon name="menu" /></button><span>Workspace <span class="breadcrumb-separator">/</span> <strong>{{ $sectionTitle }}</strong></span></div>
            <div class="topbar-actions"><span class="role-badge">{{ auth()->user()->role->label() }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="sign-out"><x-ui.icon name="logout" /><span>Sign out</span></button></form></div>
        </header>
        <aside class="app-sidebar" id="primary-navigation" aria-label="Workspace navigation">
            <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark"><x-ui.icon name="building" /></span><span>LGU Payroll<small>Accounting Office</small></span></a>
            <p class="nav-section-label">Workspace</p>
            <nav aria-label="Main navigation">
                <x-ui.nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="grid">Overview</x-ui.nav-link>
                @foreach (\App\Enums\Role::cases() as $navRole)
                    @can('access-'.$navRole->workspace())
                        <x-ui.nav-link :href="route($navRole->workspace())" :active="request()->routeIs($navRole->workspace())" icon="shield">{{ $navRole->label() }} workspace</x-ui.nav-link>
                    @endcan
                @endforeach
                <p class="nav-section-label">Management</p>
                @can('viewAny', \App\Models\Employee::class)
                    <x-ui.nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*', 'employee-salaries.*')" icon="people">Employees</x-ui.nav-link>
                @endcan
                @can('viewAny', \App\Models\PayrollBatch::class)
                    <x-ui.nav-link :href="route('payroll-batches.index')" :active="request()->routeIs('payroll-batches.*', 'payroll-employees.*', 'payroll-inputs.*', 'payroll-calculations.*')" icon="calendar">Monthly payroll drafts</x-ui.nav-link>
                @endcan
                @can('viewAny', \App\Models\SalarySchedule::class)
                    <x-ui.nav-link :href="route('salary-schedules.index')" :active="request()->routeIs('salary-schedules.*', 'salary-rates.*')" icon="layers">Salary schedules</x-ui.nav-link>
                @endcan
                @can('manage-users')
                    <x-ui.nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="people">User accounts</x-ui.nav-link>
                @endcan
                @can('viewAny', \App\Models\PayrollRuleVersion::class)
                    <x-ui.nav-link :href="route('payroll-rule-versions.index')" :active="request()->routeIs('payroll-rule-versions.*')" icon="layers">Payroll configuration</x-ui.nav-link>
                @endcan
            </nav>
            <div class="sidebar-note"><x-ui.icon name="file" /><div><strong>One clear record.</strong><p>Prepare, review and preserve your payroll information.</p></div></div>
            <div class="sidebar-account"><span class="avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role->label() }}</small></div></div>
        </aside>
        <div class="app-frame">
            <main id="main-content" class="workspace-content" tabindex="-1">
                @include('partials.feedback')
                @yield('content')
            </main>
            <footer class="app-footer"><span>LGU Payroll Management System</span><span>Accounting Office · Internal workspace</span></footer>
        </div>
    @else
        <main id="main-content" class="auth-layout" tabindex="-1">
            <section class="auth-introduction" aria-label="About LGU Payroll">
                <a href="{{ route('login') }}" class="brand"><span class="brand-mark"><x-ui.icon name="building" /></span><span>LGU Payroll<small>Accounting Office</small></span></a>
                <div class="auth-introduction-copy"><p class="eyebrow">A clearer way to work</p><h2>Payroll preparation,<br>with every detail in view.</h2><p>A dedicated workspace for employee records, authorized salary information and monthly payroll preparation.</p><div class="auth-feature"><x-ui.icon name="people" /><span>Employee records, organized.</span></div><div class="auth-feature"><x-ui.icon name="calendar" /><span>Monthly preparation, connected.</span></div><div class="auth-feature"><x-ui.icon name="layers" /><span>Previous revisions, preserved.</span></div></div>
                <p class="auth-introduction-footer">LGU Payroll Management System</p>
            </section>
            <div class="auth-form-region"><div class="auth-form-content">@include('partials.feedback')@yield('content')</div><p class="auth-footer">Authorized personnel access · Accounting Office</p></div>
        </main>
    @endauth
</body>
</html>
