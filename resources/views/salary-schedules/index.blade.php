@extends('layouts.app')

@section('title', 'Salary Schedules')
@section('heading', 'Salary Schedules')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">The LGU-adopted salary schedules and their effective periods.</p>
        @can('compensation.manage')
            <x-button icon="add" variant="primary" href="{{ route('salary-schedules.create') }}">
                + Add Schedule
            </x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$schedules" />
        <x-table label="Salary Schedules records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Schedule</th>
                    <th scope="col" class="px-4 py-3 text-left">Effective From</th>
                    <th scope="col" class="px-4 py-3 text-left">Effective To</th>
                    <th scope="col" class="px-4 py-3 text-right">Grade Steps</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($schedules as $schedule)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('salary-schedules.show', $schedule) }}" class="font-medium text-brand hover:underline">
                                {{ $schedule->schedule_name }}
                            </a>
                            <div class="text-xs text-slate-400">{{ $schedule->reference_document }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $schedule->effective_from?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $schedule->effective_to?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $schedule->grade_steps_count }}</td>
                        <td class="px-4 py-3"><x-status :value="$schedule->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <x-button icon="view" variant="secondary" href="{{ route('salary-schedules.show', $schedule) }}">View</x-button>
                            @can('compensation.manage')
                                <x-button icon="edit" variant="secondary" href="{{ route('salary-schedules.edit', $schedule) }}">Edit</x-button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No salary schedules.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $schedules->links() }}</div>
@endsection
