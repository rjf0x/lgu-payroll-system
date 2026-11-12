@props(['employee' => null, 'section' => 'employment'])

@if ($employee)
    <input type="hidden" name="employee_workspace" value="1">
    <div class="mb-5 rounded-lg border border-brand-border bg-brand-soft p-4 text-sm text-brand-ink">
        <p>Employee workspace: <strong>{{ $employee->full_name }}</strong> · {{ $employee->employee_number }}</p>
        <p class="mt-1">Saving returns you to the selected employee's workspace.</p>
        <x-button icon="back" :href="route('employees.show', $employee).'#'.$section" class="mt-2">Back to employee workspace</x-button>
    </div>
@endif
