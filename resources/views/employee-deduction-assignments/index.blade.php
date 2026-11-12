@extends('layouts.app')

@section('title', 'Employee Deductions')
@section('heading', 'Employee Deduction Assignments')

@section('content')
    @if ($filterEmployee)
        <x-card class="mb-5">
            <p class="mb-3 text-sm text-muted">Showing records for <strong>{{ $filterEmployee->full_name }}</strong> · {{ $filterEmployee->employee_number }}</p>
            <div class="flex flex-wrap gap-3">
                <x-button icon="back" :href="route('employees.show', $filterEmployee)">Back to employee workspace</x-button>
                <x-button icon="list" :href="route('employee-deduction-assignments.index')">Show all employees</x-button>
            </div>
        </x-card>
    @endif
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Standing deductions assigned to an employment record (loans, dues, savings).</p>
        @can('compensation.manage')
            <x-button icon="add" variant="primary" href="{{ route('employee-deduction-assignments.create') }}">Add Assignment</x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$assignments" />
        <x-table label="Employee Deductions records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left">Deduction</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Amount</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">%</th>
                    <th scope="col" class="px-4 py-3 text-left">Reference</th>
                    <th scope="col" class="px-4 py-3 text-left">Effective</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    <tr>
                        <td class="px-4 py-3 ui-table-primary">{{ $assignment->employmentRecord->employee->full_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->deductionType->deduction_name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $assignment->amount !== null ? '₱'.number_format((float) $assignment->amount, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $assignment->percentage !== null ? number_format((float) $assignment->percentage, 2).'%' : '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->reference_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->effective_from?->format('M d, Y') }} – {{ $assignment->effective_to?->format('M d, Y') ?? 'present' }}</td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for deduction assignment #'.$assignment->getKey()">
                                @can('compensation.manage')
                                    <x-button icon="edit" variant="secondary" href="{{ route('employee-deduction-assignments.edit', $assignment) }}">Edit</x-button>
                                @endcan
                            </x-action-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No deduction assignments.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $assignments->links() }}</div>
@endsection
