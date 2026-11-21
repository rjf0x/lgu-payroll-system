@php
    $isEdit = $assignment->exists;
    $frequencies = ['MONTHLY' => 'Monthly'];
    if ($isEdit && ! array_key_exists($assignment->frequency, $frequencies)) {
        $frequencies[$assignment->frequency] = $assignment->frequency.' (unsupported; select Monthly or remove assignment)';
    }
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('employee-earning-assignments.update', $assignment) : route('employee-earning-assignments.store') }}"
    class="ui-card space-y-5" data-submit-feedback
>
    @csrf
    <x-employee-context :employee="$workspaceEmployee ?? null" section="earnings" />
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
            :options="$earningTypes->mapWithKeys(fn ($type) => [$type->earning_type_id => $type->earning_name.($type->status !== 'ACTIVE' || ! $type->is_recurring ? ' (unsupported for automatic assignment)' : '')])"
            :selected="$assignment->earning_type_id"
            placeholder="Select an earning type"
            required
        />
        <x-form.input name="amount" label="Amount" type="number" step="0.01" :value="$assignment->amount" />
        <x-form.input name="percentage" label="Percentage" type="number" step="0.01" :value="$assignment->percentage" />
        <x-form.select
            name="frequency"
            label="Frequency"
            :options="$frequencies"
            :selected="$assignment->frequency"
            required
        />
        <div class="grid grid-cols-2 gap-5">
            <x-form.input name="effective_from" label="Effective From" type="date" :value="$assignment->effective_from?->toDateString()" required />
            <x-form.input name="effective_to" label="Effective To" type="date" :value="$assignment->effective_to?->toDateString()" />
        </div>
    </div>

    <p class="text-xs text-slate-500">Automatic assignments support active, recurring monthly earnings only. Enter annual or one-time earnings explicitly in the applicable payroll period. Provide an amount, a percentage, or both; an amount takes precedence.</p>

    <x-audit-change-context />

    <div class="flex flex-wrap gap-3">
        <x-button :icon="$isEdit ? 'save' : 'add'" type="submit" variant="primary">{{ $isEdit ? 'Save changes' : 'Create assignment' }}</x-button>
        <x-button icon="back" href="{{ $workspaceEmployee ? route('employees.show', $workspaceEmployee).'#earnings' : route('employee-earning-assignments.index') }}">Cancel</x-button>
    </div>
</form>
