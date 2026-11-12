@extends('layouts.app')

@section('title', 'Compensation Profiles')
@section('heading', 'Compensation Profiles')

@section('content')
    @if ($filterEmployee)
        <x-card class="mb-5">
            <p class="mb-3 text-sm text-muted">Showing records for <strong>{{ $filterEmployee->full_name }}</strong> · {{ $filterEmployee->employee_number }}</p>
            <div class="flex flex-wrap gap-3">
                <x-button icon="back" :href="route('employees.show', $filterEmployee)">Back to employee workspace</x-button>
                <x-button icon="list" :href="route('compensation-profiles.index')">Show all employees</x-button>
            </div>
        </x-card>
    @endif
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Grade-and-step or direct-rate compensation attached to an employment record.</p>
        @can('compensation.manage')
            <x-button icon="add" variant="primary" href="{{ route('compensation-profiles.create') }}">
                Add Compensation Profile
            </x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$profiles" />
        <x-table label="Compensation Profiles records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left">Basis</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Rate</th>
                    <th scope="col" class="px-4 py-3 text-left">Effective</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profiles as $profile)
                    <tr>
                        <td class="px-4 py-3 ui-table-primary">
                            <a href="{{ route('compensation-profiles.show', $profile) }}" class="font-medium text-brand hover:underline">
                                {{ $profile->employmentRecord->employee->full_name }}
                            </a>
                            <div class="ui-table-secondary">{{ $profile->employmentRecord->position->position_title }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $profile->gradeStep ? 'Grade & Step' : ucfirst(strtolower($profile->pay_basis)) }}
                        </td>
                        <td class="px-4 py-3 font-mono text-right tabular-nums">
                            ₱{{ number_format((float) ($profile->gradeStep->monthly_salary ?? $profile->basic_rate), 2) }}
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $profile->effective_from?->format('M d, Y') }} – {{ $profile->effective_to?->format('M d, Y') ?? 'present' }}
                        </td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for compensation profile #'.$profile->getKey()">
                                <x-button icon="view" variant="secondary" href="{{ route('compensation-profiles.show', $profile) }}">View</x-button>
                                @can('compensation.manage')
                                    <x-button icon="edit" variant="secondary" href="{{ route('compensation-profiles.edit', $profile) }}">Edit</x-button>
                                @endcan
                            </x-action-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No compensation profiles.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $profiles->links() }}</div>
@endsection
