@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-batches.index') }}" class="text-teal-800 underline">All monthly drafts</a>
    <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-bold">{{ $batch->period_start->format('F Y') }} payroll draft</h1>
        @can('update', $batch)<a href="{{ route('payroll-employees.create', $batch) }}" class="rounded bg-teal-800 px-4 py-3 text-white">Select employees</a>@endcan
    </div>
    <p class="mt-3 font-semibold">Draft · {{ $entries->total() }} employees covered · {{ $batch->is_demonstration ? 'Demonstration only — not for real payroll' : 'Externally authorized salary sources' }}</p>
    <a href="{{ route('payroll-calculations.index', $batch) }}" class="mt-3 inline-block text-teal-800 underline">Core calculation previews and history</a>
    <p class="mt-2 text-slate-600">Salary reference: {{ $batch->period_start->format('Y-m-d') }}. Saved amounts are monthly salary snapshots, not calculated pay. Source checks below apply to the entries on this page; every entry must be reviewed before later processing.</p>
    <div class="mt-6 space-y-4">
        @forelse ($entries as $entry)
            <article class="rounded border border-slate-200 bg-white p-5">
                <h2 class="text-lg font-semibold">{{ $entry->employee_number }} · {{ $entry->employee_name }}</h2>
                <p class="mt-2">{{ $entry->position }} · {{ $entry->office }} · {{ $entry->personnel_classification->label() }}</p>
                <p class="mt-2">Grade {{ $entry->grade }} / Step {{ $entry->step }} · Monthly salary PHP {{ $entry->monthly_salary }}</p>
                <a href="{{ route('payroll-inputs.show', [$batch, $entry]) }}" class="mt-3 inline-block text-teal-800 underline">Draft earnings, deductions and attendance inputs</a>
                <p class="mt-2">Source: {{ $entry->salaryRecord->rate->schedule->name }} · Effective {{ $entry->salaryRecord->effective_from->format('Y-m-d') }} · Authority: {{ $entry->salaryRecord->authority_reference }}</p>
                @if ($issues[$entry->id])
                    <p class="mt-3 font-semibold text-amber-900">Needs review: {{ $issues[$entry->id] }} Saved values remain unchanged.</p>
                @else
                    <p class="mt-3 font-semibold text-teal-800">Salary source checked · Not calculated</p>
                @endif
                @can('update', $batch)
                    <form method="POST" action="{{ route('payroll-employees.destroy', [$batch, $entry]) }}" class="mt-4">
                        @csrf @method('DELETE')
                        <input type="hidden" name="record_version" value="{{ $batch->record_version }}">
                        <button class="text-red-800 underline">Remove from draft</button>
                    </form>
                @endcan
            </article>
        @empty
            <p class="rounded bg-white p-6">No employees selected. This draft is awaiting coverage.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $entries->links() }}</div>
@endsection
