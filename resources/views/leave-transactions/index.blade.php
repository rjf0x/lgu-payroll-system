@extends('layouts.app')

@section('title', 'Leave Transactions')
@section('heading', 'Leave Transactions')

@section('content')
    <div class="mb-4 flex items-end justify-between gap-3">
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
            <x-button :href="route('leave-transactions.index')">Reset</x-button>
        </form>
    </div>

    <div class="min-w-0">
        <x-results :items="$transactions" />
        <x-table label="Leave Transactions records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Date</th>
                    <th scope="col" class="px-4 py-3 text-left">Employee</th>
                    <th scope="col" class="px-4 py-3 text-left">Leave Type</th>
                    <th scope="col" class="px-4 py-3 text-left">Type</th>
                    <th scope="col" class="px-4 py-3 text-right">Days</th>
                    <th scope="col" class="px-4 py-3 text-right">Balance After</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($transactions as $transaction)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $transaction->transaction_date?->format('M d, Y') }}</td>
                        <td class="px-4 py-3">{{ $transaction->leaveBalance->employee->full_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $transaction->leaveBalance->leaveType->leave_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $transaction->transaction_type }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $transaction->days, 2) }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $transaction->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No leave transactions.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $transactions->links() }}</div>
@endsection
