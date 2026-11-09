@extends('layouts.app')

@section('title', 'Compensation Profile')
@section('heading', 'Compensation Profile')

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</dt>
                <dd class="mt-1">
                    <a href="{{ route('employees.show', $profile->employmentRecord->employee) }}" class="text-brand hover:underline">
                        {{ $profile->employmentRecord->employee->full_name }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Position</dt>
                <dd class="mt-1">{{ $profile->employmentRecord->position->position_title }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pay Basis</dt>
                <dd class="mt-1">{{ $profile->pay_basis }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Grade &amp; Step</dt>
                <dd class="mt-1">
                    {{ $profile->gradeStep ? 'SG '.$profile->gradeStep->grade_number.' Step '.$profile->gradeStep->step_number : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Basic Rate</dt>
                <dd class="mt-1 font-mono">
                    {{ $profile->basic_rate !== null ? '₱'.number_format((float) $profile->basic_rate, 2) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Service Fraction</dt>
                <dd class="mt-1">{{ $profile->service_fraction }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Effective</dt>
                <dd class="mt-1">
                    {{ $profile->effective_from?->format('M d, Y') }} – {{ $profile->effective_to?->format('M d, Y') ?? 'present' }}
                </dd>
            </div>
        </dl>

        @can('compensation.manage')
            <div class="mt-6 flex gap-3">
                <x-button icon="edit" variant="secondary" href="{{ route('compensation-profiles.edit', $profile) }}">Edit</x-button>
                <form method="POST" action="{{ route('compensation-profiles.destroy', $profile) }}" onsubmit="return confirm('Delete this compensation profile?')" data-submit-feedback>
                    @csrf
                    @method('DELETE')
                    <x-button icon="delete" variant="danger" type="submit">Delete</x-button>
                </form>
            </div>
        @endcan
    </div>
@endsection
