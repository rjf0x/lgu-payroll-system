@php($isEdit = $assignment->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('employee-deduction-assignments.update', $assignment) : route('employee-deduction-assignments.store') }}"
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
            name="deduction_type_id"
            label="Deduction Type"
            :options="$deductionTypes->pluck('deduction_name', 'deduction_type_id')"
            :selected="$assignment->deduction_type_id"
            placeholder="Select a deduction type"
            required
        />
        <x-form.input name="amount" label="Amount" type="number" step="0.01" :value="$assignment->amount" />
        <x-form.input name="percentage" label="Percentage" type="number" step="0.01" :value="$assignment->percentage" />
        <x-form.input name="reference_number" label="Reference Number" :value="$assignment->reference_number" />
        <x-form.select
            name="status"
            label="Status"
            :options="['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive']"
            :selected="$assignment->status"
            required
        />
        <x-form.input name="effective_from" label="Effective From" type="date" :value="$assignment->effective_from?->toDateString()" required />
        <x-form.input name="effective_to" label="Effective To" type="date" :value="$assignment->effective_to?->toDateString()" />
    </div>

    <p class="text-xs text-slate-500">Provide an amount, a percentage, or both.</p>

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ $isEdit ? 'Save changes' : 'Create assignment' }}</button>
        <a href="{{ route('employee-deduction-assignments.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
