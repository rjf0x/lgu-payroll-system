@extends('layouts.app')
@section('title', 'Monthly payroll · LGU Payroll')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">Payroll preparation</p><h1>Monthly payroll</h1><p>Open a payroll month to review coverage, inputs and calculation previews.</p></div>@can('create', \App\Models\PayrollBatch::class)<a href="{{ route('payroll-batches.create') }}" class="button button-primary"><x-ui.icon name="plus" />Create monthly draft</a>@endcan</div>
    <div class="record-list">
        @forelse ($batches as $batch)
            <article class="record-list-card">
                <div class="record-list-icon"><span class="card-icon"><x-ui.icon name="calendar" /></span><div><h2><a href="{{ route('payroll-batches.show', $batch) }}">{{ $batch->period_start->format('F Y') }}</a></h2><p>{{ $batch->entries_count }} employees covered · Salary reference {{ $batch->period_start->format('F j, Y') }}</p><div class="record-tags"><span class="status-badge">{{ ucfirst($batch->status) }}</span><span @class(['status-badge', 'is-demo' => $batch->is_demonstration])>{{ $batch->is_demonstration ? 'Demonstration only' : 'Externally authorized salary sources' }}</span></div></div></div>
                <a href="{{ route('payroll-batches.show', $batch) }}" class="button button-secondary" aria-label="Open {{ $batch->period_start->format('F Y') }} {{ $batch->is_demonstration ? 'demonstration' : 'externally authorized' }} draft">Open payroll<x-ui.icon name="arrow" /></a>
            </article>
        @empty
            <div class="empty-state"><span class="card-icon"><x-ui.icon name="calendar" /></span><h2>No monthly drafts yet.</h2><p>{{ auth()->user()->can('create', \App\Models\PayrollBatch::class) ? 'Start by creating a payroll month, then select its covered employees.' : 'Monthly drafts will appear here after a Payroll Officer creates them.' }}</p>@can('create', \App\Models\PayrollBatch::class)<a href="{{ route('payroll-batches.create') }}" class="button button-primary">Create monthly draft<x-ui.icon name="arrow" /></a>@endcan</div>
        @endforelse
    </div>
    <p class="mt-6 text-sm text-slate-600">Open payroll to review its status and preserved snapshots. Calculation and approval workflows support demonstration payroll only.</p>
    <div class="mt-5">{{ $batches->links() }}</div>
@endsection
