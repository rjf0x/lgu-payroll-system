@extends('layouts.app')

@section('title', 'Deductions Report')
@section('heading', 'Deductions Report · '.$run->payrollPeriod->period_name.' #'.$run->run_number)

@section('content')
    <x-report-scope :run="$run" :organization-name="$systemSettings['organization_name']" />
    <div class="no-print mb-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" action="{{ route('reports.deductions', $run) }}" class="flex flex-wrap items-end gap-3">
            <x-form.select name="deduction_type_id" label="Deduction" :options="$groups->map(fn ($group) => $group['name'])->all()" :selected="$selectedType" placeholder="All deductions" />
            <x-button icon="filter" type="submit">Apply filter</x-button>
            <x-button icon="reset" :href="route('reports.deductions', $run)">Show all</x-button>
        </form>
        <div class="flex flex-wrap gap-3">
            <x-button icon="back" :href="route('reports.index')">Reports</x-button>
            <x-button icon="print" onclick="window.print()">Print deductions report</x-button>
        </div>
    </div>
    <p class="mb-5 text-sm text-muted">Employee deductions recorded in this payroll run. Names and amounts use saved payroll records. Employer contributions are excluded.</p>

    <section class="mb-6" aria-labelledby="deduction-totals-heading">
        <h2 id="deduction-totals-heading" class="mb-3 text-lg font-semibold">Deduction totals</h2>
        <x-table label="Deduction totals for this payroll run">
            <thead><tr><th scope="col">Deduction</th><th scope="col" class="text-right">Employees</th><th scope="col" class="text-right">Amount (PHP)</th><th scope="col" class="no-print">Report</th></tr></thead>
            <tbody>
                @forelse ($visibleGroups as $typeId => $group)
                    <tr>
                        <th scope="row">{{ $group['name'] }}</th>
                        <td class="text-right">{{ $group['employees']->count() }}</td>
                        <td class="text-right font-mono">{{ number_format($group['total'], 2) }}</td>
                        <td class="no-print"><x-button icon="view" :href="route('reports.deductions', [$run, 'deduction_type_id' => $typeId])">View report</x-button></td>
                    </tr>
                @empty
                    <tr><td colspan="4">No deductions have been recorded for this payroll run.</td></tr>
                @endforelse
            </tbody>
            @if ($visibleGroups->isNotEmpty())
                <tfoot><tr class="font-semibold"><th scope="row">Total deductions</th><td></td><td class="text-right font-mono">{{ number_format($visibleGroups->sum('total'), 2) }}</td><td class="no-print"></td></tr></tfoot>
            @endif
        </x-table>
    </section>

    @foreach ($visibleGroups as $typeId => $group)
        <section class="mb-6" aria-labelledby="deduction-{{ $typeId }}-heading">
            <h2 id="deduction-{{ $typeId }}-heading" class="mb-3 text-lg font-semibold">{{ $group['name'] }} — Employee breakdown</h2>
            <x-table :label="$group['name'].' employee deductions'">
                <thead><tr><th scope="col">Employee number</th><th scope="col">Employee</th><th scope="col">Department</th><th scope="col" class="text-right">Amount (PHP)</th></tr></thead>
                <tbody>
                    @foreach ($group['employees'] as $employee)
                        <tr>
                            <td>{{ $employee['employee_number'] }}</td>
                            <th scope="row">{{ $employee['employee_name'] }}</th>
                            <td>{{ $employee['department'] ?: 'Unassigned' }}</td>
                            <td class="text-right font-mono">{{ number_format($employee['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="font-semibold"><th scope="row" colspan="3">Total {{ $group['name'] }}</th><td class="text-right font-mono">{{ number_format($group['total'], 2) }}</td></tr></tfoot>
            </x-table>
        </section>
    @endforeach
@endsection
