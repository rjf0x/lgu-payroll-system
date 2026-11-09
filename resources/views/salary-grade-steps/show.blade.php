@extends('layouts.app')

@section('title', 'Grade Step')
@section('heading', 'SG '.$gradeStep->grade_number.' Step '.$gradeStep->step_number)

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Schedule</dt>
                <dd class="mt-1">
                    <a href="{{ route('salary-schedules.show', $gradeStep->salarySchedule) }}" class="text-brand hover:underline">
                        {{ $gradeStep->salarySchedule->schedule_name }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Grade</dt>
                <dd class="mt-1">{{ $gradeStep->grade_number }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Step</dt>
                <dd class="mt-1">{{ $gradeStep->step_number }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Monthly Salary</dt>
                <dd class="mt-1 font-mono">₱{{ number_format((float) $gradeStep->monthly_salary, 2) }}</dd>
            </div>
        </dl>

        @can('compensation.manage')
            <div class="mt-6 flex gap-3">
                <x-button icon="edit" variant="secondary" href="{{ route('salary-grade-steps.edit', $gradeStep) }}">Edit</x-button>
                <form method="POST" action="{{ route('salary-grade-steps.destroy', $gradeStep) }}" onsubmit="return confirm('Delete this grade step?')" data-submit-feedback>
                    @csrf
                    @method('DELETE')
                    <x-button icon="delete" variant="danger" type="submit">Delete</x-button>
                </form>
            </div>
        @endcan
    </div>
@endsection
