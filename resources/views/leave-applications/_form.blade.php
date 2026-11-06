@php($isEdit = $application->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('leave-applications.update', $application) : route('leave-applications.store') }}"
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
            :selected="$application->employee_id"
            placeholder="Select an employee"
            required
        />
        <x-form.select
            name="leave_type_id"
            label="Leave Type"
            :options="$leaveTypes->pluck('leave_name', 'leave_type_id')"
            :selected="$application->leave_type_id"
            placeholder="Select a leave type"
            required
        />
        <x-form.input name="start_date" label="Start Date" type="date" :value="$application->start_date?->toDateString()" required />
        <x-form.input name="end_date" label="End Date" type="date" :value="$application->end_date?->toDateString()" required />
        <x-form.input name="total_days" label="Total Days" type="number" step="0.5" :value="$application->total_days" required />
    </div>

    <x-form.textarea name="reason" label="Reason" :value="$application->reason" />

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            {{ $isEdit ? 'Save changes' : 'File leave' }}
        </button>
        <a href="{{ route('leave-applications.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
