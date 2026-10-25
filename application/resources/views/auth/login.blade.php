@extends('layouts.app')
@section('title', 'Sign in · LGU Payroll')
@section('content')
    <section class="mx-auto max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-widest text-teal-700">Accounting Office</p>
        <h1 class="mt-3 text-2xl font-bold">Sign in</h1>
        <p class="mt-2 text-sm text-slate-600">Use the account provided by your administrator.</p>
        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
            @csrf
            <div>
                <label for="username" class="block text-sm font-semibold">Username</label>
                <input id="username" name="username" type="text" value="{{ is_string(old('username')) ? old('username') : '' }}" required minlength="3" maxlength="50" autocomplete="username" autofocus class="mt-2 w-full rounded border border-slate-300 p-3">
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold">Password</label>
                <input id="password" name="password" type="password" required maxlength="72" autocomplete="current-password" class="mt-2 w-full rounded border border-slate-300 p-3">
            </div>
            <button class="w-full rounded bg-teal-800 px-4 py-3 font-semibold text-white hover:bg-teal-900">Sign in</button>
        </form>
    </section>
@endsection
