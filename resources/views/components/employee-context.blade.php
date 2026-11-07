@props(['employee' => null, 'section' => 'employment'])

@if ($employee)
    <input type="hidden" name="employee_workspace" value="1">
    <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        <p>Employee workspace: <strong>{{ $employee->full_name }}</strong> · {{ $employee->employee_number }}</p>
        <p class="mt-1">Saving returns you to the selected employee's workspace.</p>
        <a href="{{ route('employees.show', $employee).'#'.$section }}" class="mt-2 inline-flex min-h-11 items-center font-semibold underline">Back to employee workspace</a>
    </div>
@endif
