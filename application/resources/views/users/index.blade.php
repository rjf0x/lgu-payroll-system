@extends('layouts.app')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-3xl font-bold">User accounts</h1>
        <a href="{{ route('users.create') }}" class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Create account</a>
    </div>
    <div class="mt-6 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">System user accounts and assigned roles</caption>
            <thead class="bg-slate-50"><tr>
                <th scope="col" class="p-4">Name</th><th scope="col" class="p-4">Username</th><th scope="col" class="p-4">Role</th><th scope="col" class="p-4">Status</th><th scope="col" class="p-4">Action</th>
            </tr></thead>
            <tbody>
                @foreach ($users as $account)
                    <tr class="border-t border-slate-200">
                        <td class="p-4">{{ $account->name }}</td><td class="p-4">{{ $account->username }}</td><td class="p-4">{{ $account->role->label() }}</td><td class="p-4"><span @class(['status-badge', 'is-active' => $account->is_active, 'is-inactive' => ! $account->is_active])>{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="p-4"><a href="{{ route('users.edit', $account) }}" class="font-semibold text-teal-800 underline" aria-label="Edit {{ $account->name }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $users->links() }}</div>
@endsection
