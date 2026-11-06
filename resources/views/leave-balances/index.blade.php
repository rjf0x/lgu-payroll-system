@extends('layouts.app')

@section('title', 'Leave Balances')
@section('heading', 'Leave Balances')

@section('content')
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-slate-500">Employee</label>
                <select name="employee_id" onchange="this.form.submit()" class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">All employees</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->employee_id }}" @selected($employeeId === $employee->employee_id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <a href="{{ route('leave-balances.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ Add Balance</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Leave Type</th>
                    <th class="px-4 py-3 text-center">Year</th>
                    <th class="px-4 py-3 text-right">Opening</th>
                    <th class="px-4 py-3 text-right">Credits</th>
                    <th class="px-4 py-3 text-right">Used</th>
                    <th class="px-4 py-3 text-right">Available</th>
                    <th class="px-4 py-3 text-right">Actions</th>
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
        </table>
    </div>

    <div class="mt-4">{{ $balances->links() }}</div>
@endsection
