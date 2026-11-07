@extends('layouts.app')

@section('title', 'Monthly Payroll')
@section('heading', 'Monthly Payroll Runs')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex min-w-0 flex-wrap items-end gap-3">
            <select name="status" class="ui-field" aria-label="Status" id="filter-status">
                <option value="">All statuses</option>
                @foreach (['DRAFT', 'PENDING_APPROVAL', 'RETURNED', 'REJECTED', 'APPROVED', 'FINALIZED'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ str_replace('_', ' ', $option) }}</option>
                @endforeach
            </select>
            <x-button type="submit">Apply filters</x-button>
            <x-button :href="route('payroll-runs.index')">Reset</x-button>
        </form>
        @can('payroll.prepare')
            <a href="{{ route('payroll-runs.create') }}" class="ui-button ui-button-primary">+ New Payroll Run</a>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$runs" />
        <x-table label="Monthly Payroll records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Period</th>
                    <th scope="col" class="px-4 py-3 text-center">Run</th>
                    <th scope="col" class="px-4 py-3 text-left">Type</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Employees</th>
                    <th scope="col" class="px-4 py-3 text-left">Created By</th>
                    <th scope="col" class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($runs as $run)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $run->payrollPeriod->period_name }}</td>
                        <td class="px-4 py-3 text-center">#{{ $run->run_number }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $run->run_type }}</td>
                        <td class="px-4 py-3">
                            <x-status :value="$run->status" />
                        </td>
                        <td class="px-4 py-3 text-right">{{ $run->details_count }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $run->createdBy?->username }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('payroll-runs.show', $run) }}" class="text-emerald-700 hover:underline">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No payroll runs yet.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $runs->links() }}</div>
@endsection
