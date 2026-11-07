@extends('layouts.app')

@section('title', 'Leave Types')
@section('heading', 'Leave Types')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Leave categories, default credits, and paid/unpaid treatment.</p>
        <a href="{{ route('leave-types.create') }}" class="ui-button ui-button-primary">+ Add Leave Type</a>
    </div>

    <div class="min-w-0">
        <x-results :items="$leaveTypes" />
        <x-table label="Leave Types records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Leave Name</th>
                    <th scope="col" class="px-4 py-3 text-right">Default Credit</th>
                    <th scope="col" class="px-4 py-3 text-left">Paid</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
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
        </x-table>
    </div>

    <div class="mt-4">{{ $leaveTypes->links() }}</div>
@endsection
