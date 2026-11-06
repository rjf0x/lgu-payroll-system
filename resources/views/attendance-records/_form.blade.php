@php($isEdit = $record->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('attendance-records.update', $record) : route('attendance-records.store') }}"
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

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            {{ $isEdit ? 'Save changes' : 'Save record' }}
        </button>
        <a href="{{ route('attendance-records.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
