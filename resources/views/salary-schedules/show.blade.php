@extends('layouts.app')

@section('title', 'Salary Schedule')
@section('heading', $schedule->schedule_name)

@section('content')
    <div class="mb-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Effective From</dt>
                <dd class="mt-1">{{ $schedule->effective_from?->format('M d, Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Effective To</dt>
                <dd class="mt-1">{{ $schedule->effective_to?->format('M d, Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reference</dt>
                <dd class="mt-1">{{ $schedule->reference_document ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1"><x-status :value="$schedule->status" /></dd>
            </div>
        </dl>

        @can('compensation.manage')
            <div class="mt-6 flex gap-3">
                <x-button icon="edit" variant="secondary" href="{{ route('salary-schedules.edit', $schedule) }}">Edit</x-button>
                <form method="POST" action="{{ route('salary-schedules.destroy', $schedule) }}" onsubmit="return confirm('Delete this schedule and its grade steps?')" data-submit-feedback>
                    @csrf
                    @method('DELETE')
                    <x-button icon="delete" variant="danger" type="submit">Delete</x-button>
                </form>
            </div>
        @endcan
    </div>

    <div class="min-w-0">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <span class="text-sm font-semibold text-slate-700">Grade Steps</span>
            @can('compensation.manage')
                <a href="{{ route('salary-grade-steps.create', ['salary_schedule_id' => $schedule->salary_schedule_id]) }}" class="text-xs font-semibold text-brand hover:underline">+ Add Grade Step</a>
            @endcan
        </div>
        <x-table label="Salary Schedule records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Grade</th>
                    <th scope="col" class="px-4 py-3 text-left">Step</th>
                    <th scope="col" class="px-4 py-3 text-right">Monthly Salary</th>
                    <th scope="col" class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($schedule->gradeSteps as $step)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">SG {{ $step->grade_number }}</td>
                        <td class="px-4 py-3">Step {{ $step->step_number }}</td>
                        <td class="px-4 py-3 text-right font-mono">₱{{ number_format((float) $step->monthly_salary, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('compensation.manage')
                                <x-button icon="edit" variant="secondary" href="{{ route('salary-grade-steps.edit', $step) }}">Edit</x-button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No grade steps yet.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>
@endsection
