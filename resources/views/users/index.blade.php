@extends('layouts.app')

@section('title', 'User Accounts')
@section('heading', 'User Accounts')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Accounts, role assignments, and account status.</p>
        <x-button icon="add" variant="primary" href="{{ route('users.create') }}">
            Add User
        </x-button>
    </div>

    <div class="min-w-0">
        <x-results :items="$users" />
        <x-table label="User Accounts records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Username</th>
                    <th scope="col" class="px-4 py-3 text-left">Role(s)</th>
                    <th scope="col" class="px-4 py-3 text-left">Employee</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 ui-table-primary">{{ $user->username }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->roles->pluck('role_name')->implode(', ') ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->employee?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center"><x-status :value="$user->account_status" /></td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for '.$user->username">
                                <x-button icon="view" variant="secondary" href="{{ route('users.show', $user) }}">View</x-button>
                                <x-button icon="edit" variant="secondary" href="{{ route('users.edit', $user) }}">Edit</x-button>
                            </x-action-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No user accounts.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
