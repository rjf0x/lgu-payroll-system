@extends('layouts.app')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-bold">Salary schedules</h1>
        @can('create', \App\Models\SalarySchedule::class)<a href="{{ route('salary-schedules.create') }}" class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Create schedule</a>@endcan
    </div>
    <p class="mt-3 text-slate-600">Published schedules retain their original rates. Demo schedules must not be used for real payroll.</p>
    <div class="mt-6 space-y-3">
        @forelse ($schedules as $schedule)
            <article class="rounded border border-slate-200 bg-white p-5">
                <a href="{{ route('salary-schedules.show', $schedule) }}" class="text-lg font-semibold text-teal-800 underline">{{ $schedule->name }}</a>
                <p class="mt-2">{{ $schedule->personnel_classification->label() }} · Effective {{ $schedule->effective_from->format('Y-m-d') }}</p>
                <p class="mt-1 text-sm">{{ $schedule->is_demonstration ? 'Demonstration only' : 'External authorization required' }} · {{ $schedule->published_at ? 'Published / locked' : 'Draft' }}</p>
            </article>
        @empty
            <p class="rounded bg-white p-6">No salary schedules are available.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $schedules->links() }}</div>
@endsection
