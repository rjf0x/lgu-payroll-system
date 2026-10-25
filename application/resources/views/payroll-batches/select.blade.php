@extends('layouts.app')
@section('content')
    @php($selected = old('employee_ids', []))
    @php($selected = is_array($selected) ? array_map('strval', array_filter($selected, 'is_scalar')) : [])
    <a href="{{ route('payroll-batches.show', $batch) }}" class="text-teal-800 underline">Back to saved draft</a>
    <h1 class="mt-5 text-3xl font-bold">Select employees · {{ $batch->period_start->format('F Y') }}</h1>
    <p class="mt-3 font-semibold">{{ $batch->is_demonstration ? 'Demonstration only — not for real payroll' : 'Externally authorized salary sources' }}</p>
    <p class="mt-2 text-slate-600">Only eligible rows can be selected. Save this page before changing pages; previously saved coverage is retained. Already covered employees are excluded.</p>
    <form method="POST" action="{{ route('payroll-employees.store', $batch) }}" class="mt-6 space-y-4">
        @csrf
        <input type="hidden" name="record_version" value="{{ $batch->record_version }}">
        @forelse ($employees as $employee)
            @php($source = $sources[$employee->id])
            <label class="flex items-start gap-4 rounded border border-slate-200 bg-white p-5">
                <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" @disabled($source['issue'] !== null) @checked(in_array((string) $employee->id, $selected, true)) class="mt-1">
                <span><span class="block font-semibold">{{ $employee->employee_number }} · {{ $employee->full_name }}</span>
                    <span class="mt-2 block">{{ $employee->office }} · {{ $employee->personnel_classification->label() }}</span>
                    @if ($source['issue'])<span class="mt-2 block text-amber-900">Unavailable: {{ $source['issue'] }}</span>
                    @else<span class="mt-2 block">Grade {{ $source['record']->rate->grade }} / Step {{ $source['record']->rate->step }} · Monthly salary PHP {{ $source['record']->monthly_salary }}</span>@endif
                </span>
            </label>
        @empty
            <p>No additional active employees are available.</p>
        @endforelse
        @if ($employees->count())<button class="rounded bg-teal-800 px-4 py-3 text-white">Add selected employees to draft</button>@endif
    </form>
    <div class="mt-5">{{ $employees->links() }}</div>
@endsection
