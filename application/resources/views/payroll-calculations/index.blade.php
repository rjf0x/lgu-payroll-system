@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-batches.show', $batch) }}" class="text-teal-800 underline">Back to monthly draft</a>
    <h1 class="mt-5 text-3xl font-bold">Core calculation previews · {{ $batch->period_start->format('F Y') }}</h1>
    <p class="mt-3 font-semibold text-amber-900">Demonstration only — before statutory deductions. Not approved, finalized or ready for real payroll.</p>
    <p class="mt-3">The preview uses the full-month salary reference, applicable allowances, bonuses, authorized replacement adjustments and manual loan deductions. Every covered employee needs reviewed saved inputs with zero absence days and zero undertime. Missing statutory and attendance rules are not treated as zero deductions.</p>
    @if ($batch->is_demonstration)
        @can('update', $batch)
            <form method="POST" action="{{ route('payroll-calculations.store', $batch) }}" class="mt-5">
                @csrf
                <input type="hidden" name="record_version" value="{{ $batch->record_version }}">
                <button class="rounded bg-teal-800 px-4 py-3 text-white">Calculate demonstration core pay</button>
            </form>
        @endcan
    @else
        <p class="mt-5 rounded border border-amber-200 bg-white p-4">Externally authorized batches cannot be calculated by this demonstration engine. Category and statutory rules require validation first.</p>
    @endif
    <h2 class="mt-8 text-xl font-semibold">Preserved previews</h2>
    @forelse ($runs as $run)
        <p class="mt-4 rounded border border-slate-200 bg-white p-4">
            <a href="{{ route('payroll-calculations.show', [$batch, $run]) }}" class="text-teal-800 underline">Preview {{ $run->id }}</a> · Draft version {{ $run->batch_version }} · {{ $run->items_count }} employees · Provisional
            @if ($run->batch_version !== $batch->record_version)
                · Draft changed since this preview
            @endif
        </p>
    @empty<p class="mt-4">No core calculation previews saved.</p>@endforelse
    <div class="mt-5">{{ $runs->links() }}</div>
@endsection
