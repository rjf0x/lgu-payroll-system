@extends('layouts.app')

@section('title', 'Employee Deductions')
@section('heading', 'Employee Deduction Assignments')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Standing deductions assigned to an employment record (loans, dues, savings).</p>
        @can('compensation.manage')
            <a href="{{ route('employee-deduction-assignments.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ Add Assignment</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Deduction</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-right">%</th>
                    <th class="px-4 py-3 text-left">Reference</th>
                    <th class="px-4 py-3 text-left">Effective</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($assignments as $assignment)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $assignment->employmentRecord->employee->full_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->deductionType->deduction_name }}</td>
                        <td class="px-4 py-3 text-right">{{ $assignment->amount !== null ? '₱'.number_format((float) $assignment->amount, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $assignment->percentage !== null ? number_format((float) $assignment->percentage, 2).'%' : '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->reference_number ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->effective_from?->format('M d, Y') }} – {{ $assignment->effective_to?->format('M d, Y') ?? 'present' }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('compensation.manage')
                                <a href="{{ route('employee-deduction-assignments.edit', $assignment) }}" class="text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No deduction assignments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $assignments->links() }}</div>
@endsection
