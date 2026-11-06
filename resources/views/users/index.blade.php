@extends('layouts.app')

@section('title', 'User Accounts')
@section('heading', 'User Accounts')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Accounts, role assignments, and account status.</p>
        <a href="{{ route('users.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">
            + Add User
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Username</th>
                    <th class="px-4 py-3 text-left">Role(s)</th>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $user->username }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->roles->pluck('role_name')->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->employee?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3"><x-status :value="$user->account_status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('users.show', $user) }}" class="text-emerald-700 hover:underline">View</a>
                            <a href="{{ route('users.edit', $user) }}" class="ml-3 text-slate-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No user accounts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
