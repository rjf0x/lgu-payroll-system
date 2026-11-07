@extends('layouts.app')

@section('title', 'Leave Balances')
@section('heading', 'Leave Balances')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label for="filter-employee-id" class="mb-1.5 block text-sm font-semibold">Employee</label>
                <select name="employee_id" class="ui-field" id="filter-employee-id">
                    <option value="">All employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->employee_id }}" @selected($employeeId === $employee->employee_id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit">Apply filters</x-button>
            <x-button :href="route('leave-balances.index')">Reset</x-button>
        </form>

        <a href="{{ route('leave-balances.create') }}" class="ui-button ui-button-primary">+ Add Balance</a>
    </div>

    <div class="min-w-0">
        <x-results :items="$balances" />
        <x-table label="Leave Balances records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left">Leave Type</th>
                    <th scope="col" class="px-4 py-3 text-center">Year</th>
                    <th scope="col" class="px-4 py-3 text-right">Opening</th>
                    <th scope="col" class="px-4 py-3 text-right">Credits</th>
                    <th scope="col" class="px-4 py-3 text-right">Used</th>
                    <th scope="col" class="px-4 py-3 text-right">Available</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($balances as $balance)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $balance->employee->full_name }}</td>
                        <td class="px-4 py-3">{{ $balance->leaveType->leave_name }}</td>
                        <td class="px-4 py-3 text-center">{{ $balance->leave_year }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $balance->opening_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $balance->total_credits, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $balance->total_used, 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $balance->available_balance, 2) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('leave-balances.edit', $balance) }}" class="text-slate-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No leave balances.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $balances->links() }}</div>
@endsection
