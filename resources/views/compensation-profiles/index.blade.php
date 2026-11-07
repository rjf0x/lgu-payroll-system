@extends('layouts.app')

@section('title', 'Compensation Profiles')
@section('heading', 'Compensation Profiles')

@section('content')
    @if ($filterEmployee)
        <x-card class="mb-5">
            <p class="mb-3 text-sm text-muted">Showing records for <strong>{{ $filterEmployee->full_name }}</strong> · {{ $filterEmployee->employee_number }}</p>
            <div class="flex flex-wrap gap-3">
                <x-button :href="route('employees.show', $filterEmployee)">Back to employee workspace</x-button>
                <x-button :href="route('compensation-profiles.index')">Show all employees</x-button>
            </div>
        </x-card>
    @endif
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Grade-and-step or direct-rate compensation attached to an employment record.</p>
        @can('compensation.manage')
            <a href="{{ route('compensation-profiles.create') }}" class="ui-button ui-button-primary">
                + Add Compensation Profile
            </a>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$profiles" />
        <x-table label="Compensation Profiles records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left">Basis</th>
                    <th scope="col" class="px-4 py-3 text-right">Rate</th>
                    <th scope="col" class="px-4 py-3 text-left">Effective</th>
                    <th scope="col" class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($profiles as $profile)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('compensation-profiles.show', $profile) }}" class="font-medium text-emerald-700 hover:underline">
                                {{ $profile->employmentRecord->employee->full_name }}
                            </a>
                            <div class="text-xs text-slate-400">{{ $profile->employmentRecord->position->position_title }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $profile->gradeStep ? 'Grade & Step' : ucfirst(strtolower($profile->pay_basis)) }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono">
                            ₱{{ number_format((float) ($profile->gradeStep->monthly_salary ?? $profile->basic_rate), 2) }}
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $profile->effective_from?->format('M d, Y') }} – {{ $profile->effective_to?->format('M d, Y') ?? 'present' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('compensation-profiles.show', $profile) }}" class="text-emerald-700 hover:underline">View</a>
                            @can('compensation.manage')
                                <a href="{{ route('compensation-profiles.edit', $profile) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                            @endcan
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
