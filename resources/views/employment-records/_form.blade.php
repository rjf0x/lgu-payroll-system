@php($isEdit = $employmentRecord->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('employment-records.update', $employmentRecord) : route('employment-records.store') }}"
    class="ui-card space-y-5" data-submit-feedback
>
    @csrf
    <x-employee-context :employee="$workspaceEmployee ?? null" section="employment" />
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.select
            name="employee_id"
            label="Employee"
            :options="$employees->mapWithKeys(fn ($e) => [$e->employee_id => $e->employee_number.' — '.$e->full_name])"
            :selected="$employmentRecord->employee_id"
            placeholder="Select an employee"
            required
        />
        <x-form.select
            name="employment_type_id"
            label="Employment Type"
            :options="$employmentTypes->pluck('employment_type_name', 'employment_type_id')"
            :selected="$employmentRecord->employment_type_id"
            placeholder="Select a type"
            required
        />
        <x-form.select
            name="department_id"
            label="Department"
            :options="$departments->pluck('department_name', 'department_id')"
            :selected="$employmentRecord->department_id"
            placeholder="Select a department"
            required
        />
        <x-form.select
            name="position_id"
            label="Position"
            :options="$positions->mapWithKeys(fn ($p) => [$p->position_id => $p->position_title.' (SG '.($p->salary_grade ?? '—').')'])"
            :selected="$employmentRecord->position_id"
            placeholder="Select a position"
            required
        />
        <x-form.input name="appointment_number" label="Appointment Number" :value="$employmentRecord->appointment_number" />
        <x-form.select
            name="employment_status"
            label="Employment Status"
            :options="['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive']"
            :selected="$employmentRecord->employment_status"
            required
        />
        <x-form.input name="start_date" label="Start Date" type="date" :value="$employmentRecord->start_date?->toDateString()" required />
        <x-form.input name="end_date" label="End Date" type="date" :value="$employmentRecord->end_date?->toDateString()" />
    </div>

    <x-audit-change-context :employment="true" />

    <div class="flex flex-wrap gap-3">
        <x-button :icon="$isEdit ? 'save' : 'add'" type="submit" variant="primary">
            {{ $isEdit ? 'Save changes' : 'Create employment record' }}
        </x-button>
        <x-button icon="back" href="{{ $workspaceEmployee ? route('employees.show', $workspaceEmployee).'#employment' : route('employment-records.index') }}">
            Cancel
        </x-button>
    </div>
</form>
