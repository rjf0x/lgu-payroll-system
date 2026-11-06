@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-slate-800">Welcome, {{ auth()->user()->username }}</h2>
        <p class="mt-1 text-sm text-slate-500">
            You are signed in to the LGU Payroll Management System.
        </p>

        <dl class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role(s)</dt>
                <dd class="mt-1 text-sm text-slate-800">
                    {{ auth()->user()->roles->pluck('role_name')->implode(', ') ?: 'None assigned' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Permissions</dt>
                <dd class="mt-1 text-sm text-slate-800">
                    {{ auth()->user()->permissionNames()->implode(', ') ?: 'None' }}
                </dd>
            </div>
        </dl>
    </div>
@endsection
