@extends('layouts.app')
@section('content')
    @php($editable = ! $schedule->published_at && auth()->user()->can('update', $schedule))
    @php($text = function ($field) { $value = old($field); return is_scalar($value) ? (string) $value : ''; })
    <a href="{{ route('salary-schedules.index') }}" class="text-teal-800 underline">Back to schedules</a>
    <h1 class="mt-5 text-3xl font-bold">{{ $schedule->name }}</h1>
    <p class="mt-3">{{ $schedule->personnel_classification->label() }} · Effective {{ $schedule->effective_from->format('Y-m-d') }} · {{ $schedule->published_at ? 'Published / locked' : 'Draft' }}</p>
    <p class="mt-2 font-semibold text-teal-800">{{ $schedule->is_demonstration ? 'Demonstration only — not for real payroll' : 'Externally authorized schedule' }}</p>
    @if ($schedule->published_at)<p class="mt-2">Authority: {{ $schedule->authority_reference }}</p>@endif
    @if ($editable)<a href="{{ route('salary-schedules.edit', $schedule) }}" class="mt-4 inline-block text-teal-800 underline">Edit schedule details</a>@endif
    <h2 class="mt-8 text-xl font-semibold">Monthly salary by grade and step</h2>
    <div class="mt-4 space-y-3">
        @forelse ($rates as $rate)
            <div class="rounded border border-slate-200 bg-white p-4">
                @if ($editable)
                    <form method="POST" action="{{ route('salary-rates.update', [$schedule, $rate]) }}" class="flex flex-wrap items-end gap-4">
                        @csrf @method('PUT')
                        <label class="text-sm font-semibold">Grade <input name="grade" type="number" min="1" max="65535" value="{{ $rate->grade }}" required class="mt-1 block w-24 rounded border border-slate-300 p-2"></label>
                        <label class="text-sm font-semibold">Step <input name="step" type="number" min="1" max="65535" value="{{ $rate->step }}" required class="mt-1 block w-24 rounded border border-slate-300 p-2"></label>
                        <label class="text-sm font-semibold">Monthly salary (PHP) <input name="monthly_salary" inputmode="decimal" value="{{ $rate->monthly_salary }}" required maxlength="13" class="mt-1 block rounded border border-slate-300 p-2"></label>
                        <button class="rounded bg-teal-800 px-3 py-2 font-semibold text-white">Update draft rate</button>
                    </form>
                @else
                    <p>Grade {{ $rate->grade }} · Step {{ $rate->step }} · PHP {{ $rate->monthly_salary }} per month</p>
                @endif
            </div>
        @empty
            <p>No rates entered.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $rates->links() }}</div>
    @if ($editable)
        <section class="mt-8 rounded border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-semibold">Add a grade / step</h2>
            <form method="POST" action="{{ route('salary-rates.store', $schedule) }}" class="mt-4 flex flex-wrap items-end gap-4">
                @csrf
                <label class="font-semibold">Grade <input name="grade" type="number" min="1" max="65535" value="{{ $text('grade') }}" required class="mt-1 block w-24 rounded border border-slate-300 p-2"></label>
                <label class="font-semibold">Step <input name="step" type="number" min="1" max="65535" value="{{ $text('step') }}" required class="mt-1 block w-24 rounded border border-slate-300 p-2"></label>
                <label class="font-semibold">Monthly salary (PHP) <input name="monthly_salary" inputmode="decimal" value="{{ $text('monthly_salary') }}" required maxlength="13" class="mt-1 block rounded border border-slate-300 p-2"></label>
                <button class="rounded bg-teal-800 px-4 py-2 font-semibold text-white">Add rate</button>
            </form>
            <p class="mt-3 text-sm text-slate-600">Use the approved schedule's grades, steps and positive amounts with up to two decimal places. No rates are prefilled.</p>
        </section>
        <form method="POST" action="{{ route('salary-schedules.publish', $schedule) }}" class="mt-6 max-w-2xl space-y-4 rounded border border-teal-200 bg-white p-6">
            @csrf
            <h2 class="text-xl font-semibold">Publish and lock</h2>
            <label class="block font-semibold">External authority / demonstration reference<input name="authority_reference" required maxlength="255" value="{{ $text('authority_reference') }}" class="mt-2 block w-full rounded border border-slate-300 p-3"></label>
            <label class="flex items-start gap-3"><input type="checkbox" name="authorization_confirmed" value="1" required class="mt-1"><span>I confirm the source authorizes these rates for this classification and effective date, or is clearly fictional for demonstration use.</span></label>
            <p class="text-sm text-slate-600">Published rates cannot be edited. Salary assignments require their own authorization; existing employee history will be retained.</p>
            <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Publish schedule</button>
        </form>
    @endif
@endsection
