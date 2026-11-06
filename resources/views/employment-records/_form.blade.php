@php($isEdit = $employmentRecord->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('employment-records.update', $employmentRecord) : route('employment-records.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
>
    @csrf
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

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            {{ $isEdit ? 'Save changes' : 'Create employment record' }}
        </button>
        <a href="{{ route('employment-records.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
            Cancel
        </a>
    </div>
</form>
