@extends('layouts.app')

@section('title', 'Salary Schedules')
@section('heading', 'Salary Schedules')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">The LGU-adopted salary schedules and their effective periods.</p>
        @can('compensation.manage')
            <a href="{{ route('salary-schedules.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
                + Add Schedule
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Schedule</th>
                    <th class="px-4 py-3 text-left">Effective From</th>
                    <th class="px-4 py-3 text-left">Effective To</th>
                    <th class="px-4 py-3 text-right">Grade Steps</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($schedules as $schedule)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('salary-schedules.show', $schedule) }}" class="font-medium text-emerald-700 hover:underline">
                                {{ $schedule->schedule_name }}
                            </a>
                            <div class="text-xs text-slate-400">{{ $schedule->reference_document }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $schedule->effective_from?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $schedule->effective_to?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $schedule->grade_steps_count }}</td>
                        <td class="px-4 py-3"><x-status :value="$schedule->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('salary-schedules.show', $schedule) }}" class="text-emerald-700 hover:underline">View</a>
                            @can('compensation.manage')
                                <a href="{{ route('salary-schedules.edit', $schedule) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No salary schedules.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $schedules->links() }}</div>
@endsection
