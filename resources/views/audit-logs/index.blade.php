@extends('layouts.app')

@section('title', 'Audit trail')
@section('heading', 'Audit trail')

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <p class="max-w-2xl text-sm text-slate-600">Review recorded activity by user, module, action, or date. This view and its CSV export contain event metadata; before/after payloads and browser identifiers are excluded.</p>
        <a href="{{ route('audit-logs.export', $filters) }}" class="inline-flex min-h-11 items-center rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Export filtered CSV</a>
    </div>

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-lg border border-rose-300 bg-rose-50 p-4 text-rose-900">
            <h2 class="font-semibold">Check your filters</h2>
            <ul class="mt-2 list-inside list-disc text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('audit-logs.index') }}" class="mb-5 grid gap-4 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-3">
        <x-form.select name="module" label="Module" :options="$modules->mapWithKeys(fn ($module) => [$module => $module])->all()" :selected="$filters['module'] ?? null" placeholder="All modules" />
        <x-form.select name="action" label="Action" :options="$actions->mapWithKeys(fn ($action) => [$action => $action])->all()" :selected="$filters['action'] ?? null" placeholder="All actions" />
        <x-form.select name="user_id" label="User" :options="$users->pluck('username', 'user_id')->all()" :selected="$filters['user_id'] ?? null" placeholder="All users (including system events)" />
        <x-form.input name="from" label="From date (inclusive)" type="date" :value="$filters['from'] ?? null" />
        <x-form.input name="to" label="To date (inclusive)" type="date" :value="$filters['to'] ?? null" />
        <div class="flex items-end gap-3">
            <button class="min-h-11 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Apply filters</button>
            <a href="{{ route('audit-logs.index') }}" class="inline-flex min-h-11 items-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <p class="mb-3 text-sm text-slate-600">{{ number_format($logs->total()) }} matching events. Newest first. Dates use {{ config('app.timezone') }}.</p>
    <div role="region" aria-label="Audit events" tabindex="0" class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm focus-visible:outline-2 focus-visible:outline-emerald-700">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <caption class="sr-only">Recorded audit activity, newest first</caption>
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    @foreach (['Event', 'When', 'User', 'Module', 'Action', 'Record', 'IP address'] as $heading)
                        <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 tabular-nums">#{{ $log->audit_id }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->format('M d, Y H:i:s') }}</time></td>
                        <td class="px-4 py-3">{{ $log->user?->username ?? 'System / unavailable user' }}</td>
                        <td class="px-4 py-3">{{ $log->module }}</td>
                        <td class="px-4 py-3 font-medium">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->record_table ? $log->record_table.' #'.$log->record_id : 'Not applicable' }}</td>
                        <td class="px-4 py-3">{{ $log->ip_address ?? 'Unavailable' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-600">No activity matches these filters. Try a wider date range or reset the filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
