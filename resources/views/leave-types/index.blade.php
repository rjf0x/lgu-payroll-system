@extends('layouts.app')

@section('title', 'Leave Types')
@section('heading', 'Leave Types')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Leave categories, default credits, and paid/unpaid treatment.</p>
        <a href="{{ route('leave-types.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ Add Leave Type</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Leave Name</th>
                    <th class="px-4 py-3 text-right">Default Credit</th>
                    <th class="px-4 py-3 text-left">Paid</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($leaveTypes as $leaveType)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $leaveType->leave_name }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $leaveType->default_credit, 2) }}</td>
                        <td class="px-4 py-3">{{ $leaveType->is_paid ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3"><x-status :value="$leaveType->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('leave-types.edit', $leaveType) }}" class="text-slate-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No leave types.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $leaveTypes->links() }}</div>
@endsection
