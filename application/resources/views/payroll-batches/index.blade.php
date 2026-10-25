@extends('layouts.app')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-bold">Monthly payroll drafts</h1>
        @can('create', \App\Models\PayrollBatch::class)<a href="{{ route('payroll-batches.create') }}" class="rounded bg-teal-800 px-4 py-3 text-white">Create monthly draft</a>@endcan
    </div>
    <p class="mt-3 text-slate-600">Prepare employee coverage and authorized monthly salary sources. Amounts have not been calculated, approved or finalized.</p>
    <div class="mt-6 space-y-3">
        @forelse ($batches as $batch)
            <article class="rounded border border-slate-200 bg-white p-5">
                <a href="{{ route('payroll-batches.show', $batch) }}" class="font-semibold text-teal-800 underline">{{ $batch->period_start->format('F Y') }}</a>
                <p class="mt-2">Draft · {{ $batch->entries_count }} employees covered · {{ $batch->is_demonstration ? 'Demonstration only' : 'Externally authorized salary sources' }}</p>
            </article>
        @empty
            <p class="rounded bg-white p-6">No monthly drafts yet.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $batches->links() }}</div>
@endsection
