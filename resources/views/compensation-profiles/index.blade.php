@extends('layouts.app')

@section('title', 'Compensation Profiles')
@section('heading', 'Compensation Profiles')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Grade-and-step or direct-rate compensation attached to an employment record.</p>
        @can('compensation.manage')
            <a href="{{ route('compensation-profiles.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                + Add Compensation Profile
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Basis</th>
                    <th class="px-4 py-3 text-right">Rate</th>
                    <th class="px-4 py-3 text-left">Effective</th>
                    <th class="px-4 py-3"></th>
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
        </table>
    </div>

    <div class="mt-4">{{ $profiles->links() }}</div>
@endsection
