@extends('layouts.app')

@section('title', 'Employee Earnings')
@section('heading', 'Employee Earning Assignments')

@section('content')
    @if ($filterEmployee)
        <x-card class="mb-5">
            <p class="mb-3 text-sm text-muted">Showing records for <strong>{{ $filterEmployee->full_name }}</strong> · {{ $filterEmployee->employee_number }}</p>
            <div class="flex flex-wrap gap-3">
                <x-button :href="route('employees.show', $filterEmployee)">Back to employee workspace</x-button>
                <x-button :href="route('employee-earning-assignments.index')">Show all employees</x-button>
            </div>
        </x-card>
    @endif
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Recurring or one-time earnings assigned to an employment record.</p>
        @can('compensation.manage')
            <a href="{{ route('employee-earning-assignments.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ Add Assignment</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Earning</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-right">%</th>
                    <th class="px-4 py-3 text-left">Frequency</th>
                    <th class="px-4 py-3 text-left">Effective</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($assignments as $assignment)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $assignment->employmentRecord->employee->full_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->earningType->earning_name }}</td>
                        <td class="px-4 py-3 text-right">{{ $assignment->amount !== null ? '₱'.number_format((float) $assignment->amount, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $assignment->percentage !== null ? number_format((float) $assignment->percentage, 2).'%' : '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->frequency }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->effective_from?->format('M d, Y') }} – {{ $assignment->effective_to?->format('M d, Y') ?? 'present' }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('compensation.manage')
                                <a href="{{ route('employee-earning-assignments.edit', $assignment) }}" class="text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No earning assignments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $assignments->links() }}</div>
@endsection
