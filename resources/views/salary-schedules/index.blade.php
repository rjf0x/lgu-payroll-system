@extends('layouts.app')

@section('title', 'Salary Schedules')
@section('heading', 'Salary Schedules')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">The LGU-adopted salary schedules and their effective periods.</p>
        @can('compensation.manage')
            <x-button icon="add" variant="primary" href="{{ route('salary-schedules.create') }}">
                Add Schedule
            </x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$schedules" />
        <x-table label="Salary Schedules records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Schedule</th>
                    <th scope="col" class="px-4 py-3 text-left">Effective From</th>
                    <th scope="col" class="px-4 py-3 text-left">Effective To</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Grade Steps</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($schedules as $schedule)
                    <tr>
                        <td class="px-4 py-3 ui-table-primary">
                            <a href="{{ route('salary-schedules.show', $schedule) }}" class="font-medium text-brand hover:underline">
                                {{ $schedule->schedule_name }}
                            </a>
                            <div class="ui-table-secondary">{{ $schedule->reference_document }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $schedule->effective_from?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $schedule->effective_to?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $schedule->grade_steps_count }}</td>
                        <td class="px-4 py-3 text-center"><x-status :value="$schedule->status" /></td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for '.$schedule->schedule_name">
                                <x-button icon="view" variant="secondary" href="{{ route('salary-schedules.show', $schedule) }}">View</x-button>
                                @can('compensation.manage')
                                    <x-button icon="edit" variant="secondary" href="{{ route('salary-schedules.edit', $schedule) }}">Edit</x-button>
                                @endcan
                            </x-action-menu>
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
