@extends('layouts.app')
@section('content')
    @php($period = old('period', today()->format('Y-m')))
    @php($mode = old('is_demonstration', '1'))
    <h1 class="text-3xl font-bold">Create monthly payroll draft</h1>
    <form method="POST" action="{{ route('payroll-batches.store') }}" class="mt-6 max-w-2xl space-y-5 rounded border border-slate-200 bg-white p-6">
        @csrf
        <label class="block font-semibold">Payroll month<input name="period" type="month" value="{{ is_string($period) ? $period : '' }}" min="1000-01" max="9999-12" required class="mt-2 block rounded border border-slate-300 p-3"></label>
        <label class="block font-semibold">Data use<select name="is_demonstration" class="mt-2 block w-full rounded border border-slate-300 p-3"><option value="1" @selected((string) (is_scalar($mode) ? $mode : '') === '1')>Demonstration only</option><option value="0" @selected((string) (is_scalar($mode) ? $mode : '') === '0')>Externally authorized salary sources</option></select></label>
        <p class="text-sm text-slate-600">One batch per month and data use. The salary reference date is the first day of the month. Mid-month employment or salary changes require validated treatment before inclusion. This draft does not calculate pay.</p>
        <button class="rounded bg-teal-800 px-4 py-3 text-white">Create draft</button>
        <a href="{{ route('payroll-batches.index') }}" class="ml-4 text-teal-800 underline">Cancel</a>
    </form>
@endsection
