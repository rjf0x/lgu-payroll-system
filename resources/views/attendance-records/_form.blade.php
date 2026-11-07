@php($isEdit = $record->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('attendance-records.update', $record) : route('attendance-records.store') }}"
    class="ui-card space-y-5" data-submit-feedback
>
    @csrf
    <x-employee-context :employee="$workspaceEmployee ?? null" section="attendance" />
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.select
            name="employee_id"
            label="Employee"
            :options="$employees->mapWithKeys(fn ($e) => [$e->employee_id => $e->employee_number.' — '.$e->full_name])"
            :selected="$record->employee_id"
            placeholder="Select an employee"
            required
        />
        <x-form.input name="attendance_date" label="Date" type="date" :value="$record->attendance_date?->toDateString()" required />
        <x-form.select
            name="attendance_status"
            label="Status"
            :options="[
                'PRESENT' => 'Present',
                'HALF_DAY' => 'Half Day',
                'ABSENT' => 'Absent',
                'ON_LEAVE' => 'On Leave',
                'HOLIDAY' => 'Holiday',
                'REST_DAY' => 'Rest Day',
            ]"
            :selected="$record->attendance_status"
            required
        />
        <x-form.input name="credited_days" label="Credited Days" type="number" step="0.25" :value="$record->credited_days" required />
        <x-form.input name="source" label="Source" :value="$record->source" />
    </div>

    <x-form.textarea name="remarks" label="Remarks" :value="$record->remarks" />

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="ui-button ui-button-primary">
            {{ $isEdit ? 'Save changes' : 'Save record' }}
        </button>
        <a href="{{ $workspaceEmployee ? route('employees.show', $workspaceEmployee).'#attendance' : route('attendance-records.index') }}" class="ui-button ui-button-secondary">Cancel</a>
    </div>
</form>
