@php($isEdit = $assignment->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('employee-earning-assignments.update', $assignment) : route('employee-earning-assignments.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.select
            name="employment_id"
            label="Employment Record"
            :options="$employments->mapWithKeys(fn ($e) => [$e->employment_id => $e->employee->employee_number.' — '.$e->employee->full_name.' ('.$e->position->position_title.')'])"
            :selected="$assignment->employment_id"
            placeholder="Select an employment record"
            required
        />
        <x-form.select
            name="earning_type_id"
            label="Earning Type"
            :options="$earningTypes->pluck('earning_name', 'earning_type_id')"
            :selected="$assignment->earning_type_id"
            placeholder="Select an earning type"
            required
        />
        <x-form.input name="amount" label="Amount" type="number" step="0.01" :value="$assignment->amount" />
        <x-form.input name="percentage" label="Percentage" type="number" step="0.01" :value="$assignment->percentage" />
        <x-form.select
            name="frequency"
            label="Frequency"
            :options="['MONTHLY' => 'Monthly', 'ANNUAL' => 'Annual', 'ONE_TIME' => 'One-time']"
            :selected="$assignment->frequency"
            required
        />
        <div class="grid grid-cols-2 gap-5">
            <x-form.input name="effective_from" label="Effective From" type="date" :value="$assignment->effective_from?->toDateString()" required />
            <x-form.input name="effective_to" label="Effective To" type="date" :value="$assignment->effective_to?->toDateString()" />
        </div>
    </div>

    <p class="text-xs text-slate-500">Provide an amount, a percentage, or both.</p>

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ $isEdit ? 'Save changes' : 'Create assignment' }}</button>
        <a href="{{ route('employee-earning-assignments.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
