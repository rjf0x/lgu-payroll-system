@extends('layouts.app')

@section('title', 'Employment Record')
@section('heading', 'Employment Record')

@section('content')
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</dt>
                    <dd class="mt-1">
                        <a href="{{ route('employees.show', $employmentRecord->employee) }}" class="text-brand hover:underline">
                            {{ $employmentRecord->employee->full_name }}
                        </a>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Position</dt>
                    <dd class="mt-1">{{ $employmentRecord->position->position_title }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Department</dt>
                    <dd class="mt-1">{{ $employmentRecord->department->department_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employment Type</dt>
                    <dd class="mt-1">{{ $employmentRecord->employmentType->employment_type_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Appointment No.</dt>
                    <dd class="mt-1">{{ $employmentRecord->appointment_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Period</dt>
                    <dd class="mt-1">
                        {{ $employmentRecord->start_date?->format('M d, Y') }}
                        – {{ $employmentRecord->end_date?->format('M d, Y') ?? 'present' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1"><x-status :value="$employmentRecord->employment_status" /></dd>
                </div>
            </dl>

            @can('employees.manage')
                <div class="mt-6 flex gap-3">
                    <x-button icon="edit" variant="secondary" href="{{ route('employment-records.edit', $employmentRecord) }}">Edit</x-button>
                    <form method="POST" action="{{ route('employment-records.destroy', $employmentRecord) }}" onsubmit="return confirm('Delete this employment record?')" data-submit-feedback>
                        @csrf
                        @method('DELETE')
                        <x-button icon="delete" variant="danger" type="submit">Delete</x-button>
                    </form>
                </div>
            @endcan
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <span class="text-sm font-semibold text-slate-700">Compensation Profiles</span>
                @can('compensation.manage')
                    <x-button icon="add" :href="route('compensation-profiles.create', ['employment_id' => $employmentRecord->employment_id])" class="ui-button-compact">Add</x-button>
                @endcan
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($employmentRecord->compensationProfiles as $profile)
                    <li class="flex items-center justify-between px-6 py-3">
                        <a href="{{ route('compensation-profiles.show', $profile) }}" class="text-brand hover:underline">
                            {{ $profile->gradeStep
                                ? 'SG '.$profile->gradeStep->grade_number.' Step '.$profile->gradeStep->step_number.' — ₱'.number_format((float) $profile->gradeStep->monthly_salary, 2)
                                : ucfirst(strtolower($profile->pay_basis)).' — ₱'.number_format((float) $profile->basic_rate, 2) }}
                        </a>
                        <span class="text-xs text-slate-500">
                            {{ $profile->effective_from?->format('M d, Y') }} – {{ $profile->effective_to?->format('M d, Y') ?? 'present' }}
                        </span>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-slate-500">No compensation profiles.</li>
                @endforelse
            </ul>
        </div>
    </div>
    @can('employees.manage')
        <section class="mt-6 min-w-0" aria-labelledby="employment-history-heading">
            <h2 id="employment-history-heading" class="mb-3 text-lg font-semibold">Employment history</h2>
            <x-table label="Employment history records">
                <thead>
                    <tr>
                        @foreach (['Effective date', 'Change', 'From', 'To', 'Recorded by', 'Notes'] as $heading)
                            <th scope="col" class="px-4 py-3 text-left">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employmentRecord->history as $entry)
                        <tr>
                            <td class="px-4 py-3">{{ $entry->effective_date?->format('M d, Y') }}</td>
                            <td class="px-4 py-3">{{ str_replace('_', ' ', $entry->change_type) }}</td>
                            <td class="px-4 py-3">{{ $entry->fromDepartment?->department_name ?? 'Not recorded' }} / {{ $entry->fromPosition?->position_title ?? 'Not recorded' }}</td>
                            <td class="px-4 py-3">{{ $entry->toDepartment?->department_name ?? 'Not recorded' }} / {{ $entry->toPosition?->position_title ?? 'Not recorded' }}</td>
                            <td class="px-4 py-3">{{ $entry->recordedBy?->username ?? 'Unavailable user' }}</td>
                            <td class="px-4 py-3 whitespace-normal break-words">{{ $entry->remarks }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-muted">No employment changes recorded.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
        </section>
    @endcan
@endsection
