@php($isEdit = $application->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('leave-applications.update', $application) : route('leave-applications.store') }}"
    class="ui-card space-y-5" data-submit-feedback
>
    @csrf
    <x-employee-context :employee="$workspaceEmployee ?? null" section="leave" />
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

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="ui-button ui-button-primary">
            {{ $isEdit ? 'Save changes' : 'File leave' }}
        </button>
        <a href="{{ $workspaceEmployee ? route('employees.show', $workspaceEmployee).'#leave' : route('leave-applications.index') }}" class="ui-button ui-button-secondary">Cancel</a>
    </div>
</form>
