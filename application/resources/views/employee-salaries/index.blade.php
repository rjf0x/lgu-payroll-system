@extends('layouts.app')
@section('content')
    <a href="{{ route('employees.show', $employee) }}" class="text-teal-800 underline">Back to employee</a>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-bold">Salary history · {{ $employee->full_name }}</h1>
        @can('update', $employee)<a href="{{ route('employee-salaries.create', $employee) }}" class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Record authorized change</a>@endcan
    </div>
    <form method="GET" action="{{ route('employee-salaries.index', $employee) }}" class="mt-6 flex flex-wrap items-end gap-3">
        <label class="font-semibold">Salary as of<input name="as_of" type="date" value="{{ $date }}" min="1000-01-01" max="9999-12-31" required class="mt-2 block rounded border border-slate-300 bg-white p-3"></label>
        <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Retrieve salary</button>
    </form>
    <section class="mt-6 rounded border border-slate-200 bg-white p-6">
        @if ($current)
            <h2 class="text-xl font-semibold">PHP {{ $current->monthly_salary }} per month</h2>
            <p class="mt-2">Grade {{ $current->rate->grade }} · Step {{ $current->rate->step }} · {{ $current->personnel_classification->label() }} · {{ $current->position }}</p>
            <p class="mt-2 font-semibold text-teal-800">{{ $current->is_demonstration ? 'Demonstration only — not for real payroll' : 'Recorded external salary authorization' }}</p>
            <p class="mt-2">Effective {{ $current->effective_from->format('Y-m-d') }} · {{ $current->rate->schedule->name }} · Authority: {{ $current->authority_reference }}</p>
            @if ($latestSchedule && $latestSchedule->id !== $current->rate->salary_schedule_id)<p class="mt-4 text-amber-900">A different schedule now applies. A new authorized employee salary record is required; this recorded amount has not been recalculated.</p>@endif
            @if ($current->personnel_classification !== $employee->personnel_classification)<p class="mt-4 text-amber-900">The employee classification has changed. Review salary applicability before use.</p>@endif
            <p class="mt-4">Potential three-year review: {{ $review['date'] }} {{ $review['due'] ? '(review due)' : '(not yet due)' }}</p>
            <p class="mt-2 text-sm text-slate-600">Provisional reminder based on the recorded service basis. Category-specific eligibility is unverified; no salary or step changes automatically.</p>
        @else
            <h2 class="text-xl font-semibold">No authorized salary recorded for this date</h2>
            <p class="mt-2 text-slate-600">Missing salary is not treated as zero.</p>
        @endif
    </section>
    <h2 class="mt-8 text-xl font-semibold">Preserved records, including future changes</h2>
    <div class="mt-4 space-y-3">
        @forelse ($history as $record)
            <article class="rounded border border-slate-200 bg-white p-5">
                <p class="font-semibold">{{ $record->effective_from->format('Y-m-d') }} · {{ str_replace('_', ' ', $record->change_type) }} · PHP {{ $record->monthly_salary }}</p>
                <p class="mt-2">Grade {{ $record->rate->grade }} / Step {{ $record->rate->step }} · {{ $record->position }} · {{ $record->personnel_classification->label() }}</p>
                <p class="mt-2">{{ $record->rate->schedule->name }} · {{ $record->is_demonstration ? 'Demonstration only' : 'External authorization' }}</p>
                <p class="mt-2">Authority: {{ $record->authority_reference }} · Reason: {{ $record->reason }}</p>
                <p class="mt-2 text-sm text-slate-600">Service review basis: {{ $record->step_basis_date->format('Y-m-d') }}</p>
            </article>
        @empty
            <p>No salary history yet.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $history->links() }}</div>
@endsection
