@extends('layouts.app')
@section('content')
    <h1 class="text-3xl font-bold">Dashboard</h1>
    <p class="mt-3 text-slate-600">Signed in as {{ auth()->user()->role->label() }}.</p>
    <section class="mt-8 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">Your workspace</h2>
        <a class="mt-4 inline-block font-semibold text-teal-800 underline" href="{{ route(auth()->user()->role->workspace()) }}">Open {{ auth()->user()->role->label() }} workspace</a>
        @can('manage-users')
            <p class="mt-4"><a href="{{ route('users.index') }}" class="font-semibold text-teal-800 underline">Manage user accounts</a></p>
        @endcan
    </section>
@endsection
