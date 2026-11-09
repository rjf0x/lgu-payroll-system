@extends('layouts.app')

@section('title', 'Salary Grade Steps')
@section('heading', 'Salary Grade Steps')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex min-w-0 flex-wrap items-end gap-3">
            <select name="salary_schedule_id" class="ui-field" aria-label="Salary schedule" id="filter-salary-schedule-id">
                <option value="">All schedules</option>
                @foreach ($schedules as $schedule)
                    <option value="{{ $schedule->salary_schedule_id }}" @selected($scheduleId === $schedule->salary_schedule_id)>
                        {{ $schedule->schedule_name }}
                    </option>
                @endforeach
            </select>
            <x-button icon="filter" type="submit">Apply filters</x-button>
            <x-button icon="reset" :href="route('salary-grade-steps.index')">Reset</x-button>
        </form>

        @can('compensation.manage')
            <x-button icon="add" variant="primary" href="{{ route('salary-grade-steps.create', ['salary_schedule_id' => $scheduleId]) }}">
                + Add Grade Step
            </x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$gradeSteps" />
        <x-table label="Salary Grade Steps records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Schedule</th>
                    <th scope="col" class="px-4 py-3 text-left">Grade</th>
                    <th scope="col" class="px-4 py-3 text-left">Step</th>
                    <th scope="col" class="px-4 py-3 text-right">Monthly Salary</th>
                    <th scope="col" class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($gradeSteps as $step)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600">{{ $step->salarySchedule->schedule_name }}</td>
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
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No grade steps.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $gradeSteps->links() }}</div>
@endsection
