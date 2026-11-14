@extends('layouts.app')

@section('title', 'Monthly Payroll')
@section('heading', 'Monthly Payroll Runs')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex min-w-0 flex-wrap items-end gap-3">
            @if (request()->filled('payroll_period_id'))
                <input type="hidden" name="payroll_period_id" value="{{ request()->integer('payroll_period_id') }}">
                <span class="text-sm text-slate-600">Runs for selected payroll period</span>
            @endif
            <select name="status" class="ui-field" aria-label="Status" id="filter-status" onchange="this.form.requestSubmit()" style="width: auto; min-width: 180px; max-width: 240px; min-height: 36px; max-height: 40px;">
                <option value="">All statuses</option>
                @foreach (['DRAFT', 'PENDING_APPROVAL', 'RETURNED', 'REJECTED', 'APPROVED', 'FINALIZED'] as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ str_replace('_', ' ', $option) }}</option>
                @endforeach
            </select>
            <x-button icon="reset" :href="route('payroll-runs.index')">Reset</x-button>
        </form>
        @can('payroll.prepare')
            <x-button icon="add" variant="primary" href="{{ route('payroll-runs.create') }}">New Payroll Run</x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$runs" />
        <x-table label="Monthly Payroll records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Period</th>
                    <th scope="col" class="px-4 py-3 text-left">Run</th>
                    <th scope="col" class="px-4 py-3 text-left">Type</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Employees</th>
                    <th scope="col" class="px-4 py-3 text-left">Created By</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td class="px-4 py-3 font-medium ui-table-primary">{{ $run->payrollPeriod->period_name }}</td>
                        <td class="px-4 py-3 text-left">#{{ $run->run_number }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $run->run_type }}</td>
                        <td class="px-4 py-3 text-center">
                            <x-status :value="$run->status" />
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $run->details_count }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $run->createdBy?->username }}</td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for payroll run #'.$run->run_number">
                                <x-button icon="view" variant="secondary" href="{{ route('payroll-runs.show', $run) }}">Open</x-button>
                            </x-action-menu>
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
