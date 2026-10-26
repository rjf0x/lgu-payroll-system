@extends('layouts.app')
@section('title', 'Salary schedules · LGU Payroll')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">Authorized compensation sources</p><h1>Salary schedules</h1><p>Effective-dated grades and steps, organized by personnel classification.</p></div>@can('create', \App\Models\SalarySchedule::class)<a href="{{ route('salary-schedules.create') }}" class="button button-primary"><x-ui.icon name="plus" />Create schedule</a>@endcan</div>
    <p class="text-sm text-slate-600">Published schedules are locked. Demonstration rates must not be used for real payroll.</p>
    <div class="record-list">
        @forelse ($schedules as $schedule)
            <article class="record-list-card"><div class="record-list-icon"><span class="card-icon"><x-ui.icon name="layers" /></span><div><h2><a href="{{ route('salary-schedules.show', $schedule) }}">{{ $schedule->name }}</a></h2><p>{{ $schedule->personnel_classification->label() }} · Effective {{ $schedule->effective_from->format('Y-m-d') }}</p><div class="record-tags"><span @class(['status-badge', 'is-active' => $schedule->published_at])>{{ $schedule->published_at ? 'Published / locked' : 'Draft' }}</span><span @class(['status-badge', 'is-demo' => $schedule->is_demonstration])>{{ $schedule->is_demonstration ? 'Demonstration only' : 'External authorization required' }}</span></div></div></div><a href="{{ route('salary-schedules.show', $schedule) }}" class="button button-secondary" aria-label="View {{ $schedule->name }}">View schedule<x-ui.icon name="arrow" /></a></article>
        @empty
            <div class="empty-state"><span class="card-icon"><x-ui.icon name="layers" /></span><h2>No salary schedules are available.</h2><p>{{ auth()->user()->can('create', \App\Models\SalarySchedule::class) ? 'Create a schedule, enter its rates and publish it with an authorization reference.' : 'Published schedules will appear here after an Administrator authorizes them.' }}</p>@can('create', \App\Models\SalarySchedule::class)<a href="{{ route('salary-schedules.create') }}" class="button button-primary">Create schedule<x-ui.icon name="arrow" /></a>@endcan</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $schedules->links() }}</div>
@endsection
