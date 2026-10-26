@extends('layouts.app')
@section('content')
    @php($text = function ($field, $default = '') { $value = old($field, $default); return is_scalar($value) ? (string) $value : ''; })
    <a href="{{ route('employee-salaries.index', $employee) }}" class="text-teal-800 underline">Back to salary history</a>
    <h1 class="mt-5 text-3xl font-bold">Record authorized salary change</h1>
    <p class="mt-3">{{ $employee->full_name }} · {{ $employee->personnel_classification->label() }}</p>
    <form method="GET" action="{{ route('employee-salaries.create', $employee) }}" class="mt-6 flex flex-wrap items-end gap-4 rounded border border-slate-200 bg-white p-5">
        <label class="font-semibold">Effective from<input name="effective_from" type="date" value="{{ $date }}" min="1000-01-01" max="9999-12-31" required class="mt-2 block rounded border border-slate-300 p-3"></label>
        <x-ui.field-error field="effective_from" />
        <label class="font-semibold">Data use<select name="is_demonstration" class="mt-2 block rounded border border-slate-300 p-3"><option value="1" @selected($demo === '1')>Demonstration only</option><option value="0" @selected($demo === '0')>Externally authorized</option></select></label>
            <x-ui.field-error field="is_demonstration" />
        <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Find applicable schedule</button>
    </form>
    @if (! $schedule)
        <p class="mt-6 rounded border border-amber-200 bg-white p-5">No published schedule matches this category, date, and data use. An Administrator must enter an authorized schedule first.</p>
    @else
        <p class="mt-6 font-semibold text-teal-800">{{ $schedule->name }} · {{ $schedule->is_demonstration ? 'Demonstration only — not for real payroll' : 'Externally authorized' }}</p>
        <form method="POST" action="{{ route('employee-salaries.store', $employee) }}" class="mt-4 max-w-3xl space-y-5 rounded border border-slate-200 bg-white p-6">
            @csrf
            <input type="hidden" name="effective_from" value="{{ $date }}">
            <x-ui.field-error field="effective_from" />
            <label class="block font-semibold">Authorized grade and step<select name="salary_rate_id" required class="mt-2 block w-full rounded border border-slate-300 p-3">
                <option value="">Select a rate</option>
                @foreach ($rates as $rate)<option value="{{ $rate->id }}" @selected($text('salary_rate_id') === (string) $rate->id)>Grade {{ $rate->grade }} / Step {{ $rate->step }} — PHP {{ $rate->monthly_salary }} per month</option>@endforeach
            </select></label>
            <x-ui.field-error field="salary_rate_id" />
            <label class="block font-semibold">Change type<select name="change_type" required class="mt-2 block w-full rounded border border-slate-300 p-3">
                @foreach (['initial' => 'Initial assignment', 'step_increment' => 'Step increment within the same grade', 'promotion' => 'Promotion to a different grade', 'schedule_update' => 'Updated schedule, same grade and step'] as $value => $label)<option value="{{ $value }}" @selected($text('change_type', 'initial') === $value)>{{ $label }}</option>@endforeach
            </select></label>
            <x-ui.field-error field="change_type" />
            <label class="block font-semibold">Authorized position<input name="position" value="{{ $text('position', $employee->position) }}" required maxlength="150" class="mt-2 block w-full rounded border border-slate-300 p-3"></label>
            <x-ui.field-error field="position" />
            <label class="block font-semibold">Service basis date for provisional step review<input name="step_basis_date" type="date" value="{{ $text('step_basis_date', $date) }}" min="1000-01-01" max="{{ $date }}" required class="mt-2 block w-full rounded border border-slate-300 p-3"></label>
            <x-ui.field-error field="step_basis_date" />
            <p class="text-sm text-slate-600">Use the basis stated in the external authorization. A schedule-only update retains the previous basis. The three-year reminder does not establish category eligibility or authorize a step change.</p>
            <label class="block font-semibold">External authority / demonstration reference<input name="authority_reference" value="{{ $text('authority_reference') }}" required maxlength="255" class="mt-2 block w-full rounded border border-slate-300 p-3"></label>
            <x-ui.field-error field="authority_reference" />
            <label class="block font-semibold">Reason<textarea name="reason" required maxlength="500" class="mt-2 block w-full rounded border border-slate-300 p-3">{{ $text('reason') }}</textarea></label>
            <x-ui.field-error field="reason" />
            <label class="flex items-start gap-3"><input name="authorization_confirmed" type="checkbox" value="1" required class="mt-1"><span>I confirm this change has the stated external authorization, or uses clearly fictional demonstration data.</span></label>
            <x-ui.field-error field="authorization_confirmed" />
            <p class="text-sm text-slate-600">Records cannot be edited or deleted. Subsequent changes must be today or later and after the last recorded effective date. Future-dated position details are preserved here without rewriting the employee profile.</p>
            <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Record salary change</button>
        </form>
    @endif
@endsection
