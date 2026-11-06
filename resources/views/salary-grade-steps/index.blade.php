@extends('layouts.app')

@section('title', 'Salary Grade Steps')
@section('heading', 'Salary Grade Steps')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <select name="salary_schedule_id" class="rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm" onchange="this.form.submit()">
                <option value="">All schedules</option>
                @foreach ($schedules as $schedule)
                    <option value="{{ $schedule->salary_schedule_id }}" @selected($scheduleId === $schedule->salary_schedule_id)>
                        {{ $schedule->schedule_name }}
                    </option>
                @endforeach
            </select>
        </form>

        @can('compensation.manage')
            <a href="{{ route('salary-grade-steps.create', ['salary_schedule_id' => $scheduleId]) }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                + Add Grade Step
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Schedule</th>
                    <th class="px-4 py-3 text-left">Grade</th>
                    <th class="px-4 py-3 text-left">Step</th>
                    <th class="px-4 py-3 text-right">Monthly Salary</th>
                    <th class="px-4 py-3"></th>
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
                                <a href="{{ route('salary-grade-steps.edit', $step) }}" class="text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No grade steps.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $gradeSteps->links() }}</div>
@endsection
