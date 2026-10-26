@extends('layouts.app')
@section('title', 'Overview · LGU Payroll')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">Your daily workspace</p><h1>Dashboard</h1><p>A clear starting point for your payroll work.</p></div><span class="page-date">{{ now()->format('l, F j, Y') }}</span></div>
    <section class="welcome-panel"><div><span class="role-badge">{{ auth()->user()->role->label() }}</span><h2>Welcome, {{ auth()->user()->name }}.</h2><p>{{ auth()->user()->role === \App\Enums\Role::Administrator ? 'Keep account access and authorized salary schedules organized for your team.' : 'Pick up your employee records or monthly payroll preparation from one place.' }}</p><a href="{{ route(auth()->user()->role->workspace()) }}" class="button button-primary">Open {{ auth()->user()->role->label() }} workspace <x-ui.icon name="arrow" /></a></div><div class="welcome-mark" aria-hidden="true"><x-ui.icon name="building" /></div></section>
    <div class="section-heading"><div><h2>Where would you like to start?</h2><p>Tools available for your assigned role.</p></div></div>
    @include('partials.workspace-cards')
    <section class="workspace-guidance"><x-ui.icon name="file" /><div><h2>Keep a clear preparation trail</h2><p>Review source information before saving. Employee salary history, input revisions and calculation previews retain previous values for reference.</p></div></section>
@endsection
