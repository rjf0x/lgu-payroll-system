@extends('layouts.app')
@section('content')
    @php
        $text = function ($field, $default = '') { $value = old($field, $default); return is_scalar($value) ? (string) $value : ''; };
    @endphp
    <h1 class="text-3xl font-bold">{{ $schedule->exists ? 'Edit draft salary schedule' : 'Create salary schedule' }}</h1>
    <form method="POST" action="{{ $schedule->exists ? route('salary-schedules.update', $schedule) : route('salary-schedules.store') }}" class="mt-6 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($schedule->exists) @method('PUT') @endif
        <div><label for="name" class="block font-semibold">Schedule name</label><input id="name" name="name" value="{{ $text('name', $schedule->name) }}" required maxlength="150" class="mt-2 w-full rounded border border-slate-300 p-3"></div>
        <x-ui.field-error field="name" />
        @php($selectedScope = $text('department_scope', $schedule->department === null ? 'all' : 'department:'.$schedule->department))
        <div><label for="department" class="block font-semibold">Covered department</label>
            <select id="department" name="department_scope" required class="mt-2 w-full rounded border border-slate-300 p-3">
                <option value="all" @selected($selectedScope === 'all')>All departments</option>
                @foreach ($departments as $department)<option value="{{ 'department:'.$department }}" @selected(str_starts_with($selectedScope, 'department:') && \App\Models\SalarySchedule::coverageKey(substr($selectedScope, strlen('department:'))) === \App\Models\SalarySchedule::coverageKey($department))>{{ $department }}</option>@endforeach
            </select>
            <p class="mt-2 text-sm text-slate-600">Choose all departments or one department. Names come from employee Office / department records. A department-specific published schedule takes priority over an all-departments schedule.</p>
            @if ($schedule->exists && $schedule->coverage_key === null)<p class="mt-2 text-amber-900">This draft used {{ $schedule->coverage_label }}. Saving assigns the department coverage selected above.</p>@endif
            <x-ui.field-error field="department_scope" />
        </div>
        <div><label for="effective_from" class="block font-semibold">Effective from</label><input id="effective_from" name="effective_from" type="date" value="{{ $text('effective_from', $schedule->effective_from?->format('Y-m-d')) }}" min="1000-01-01" max="9999-12-31" required class="mt-2 w-full rounded border border-slate-300 p-3"></div>
        <x-ui.field-error field="effective_from" />
        <div><label for="data-use" class="block font-semibold">Data use</label>
            <select id="data-use" name="is_demonstration" required class="mt-2 w-full rounded border border-slate-300 p-3">
                <option value="1" @selected($text('is_demonstration', $schedule->exists ? (int) $schedule->is_demonstration : 1) === '1')>Demonstration only</option>
                <option value="0" @selected($text('is_demonstration', $schedule->exists ? (int) $schedule->is_demonstration : 1) === '0')>Externally authorized LGU schedule</option>
            </select>
            <x-ui.field-error field="is_demonstration" />
        </div>
        <p class="text-sm text-slate-600">Enter approved rates separately. Publishing authorizes the selected department coverage and permanently locks this schedule.</p>
        <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Save draft</button>
        <a href="{{ route('salary-schedules.index') }}" class="ml-4 text-teal-800 underline">Cancel</a>
    </form>
@endsection
