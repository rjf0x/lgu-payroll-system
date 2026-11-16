@extends('layouts.app')

@section('title', 'User Account')
@section('heading', $user->username)

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Username</dt>
                <dd class="mt-1 font-medium text-slate-800">{{ $user->username }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1">{{ $user->email ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1"><x-status :value="$user->account_status" /></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Linked Employee</dt>
                <dd class="mt-1">{{ $user->employee?->full_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Login</dt>
                <dd class="mt-1"><x-local-time :value="$user->last_login_at" fallback="Never" /></dd>
            </div>
        </dl>

        <div class="mt-6">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role Assignments</h3>
            <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm">
                @forelse ($user->userRoles as $userRole)
                    <li class="flex items-center justify-between px-4 py-2">
                        <span>{{ $userRole->role->role_name }}</span>
                        <span class="text-xs text-slate-500">
                            <x-local-time :value="$userRole->assigned_at" format="M d, Y" />
                            @if ($userRole->assignedBy) · by {{ $userRole->assignedBy->username }} @endif
                        </span>
                    </li>
                @empty
                    <li class="px-4 py-3 text-slate-500">No roles assigned.</li>
                @endforelse
            </ul>
        </div>

        <div class="mt-6 flex gap-3">
            <x-button icon="edit" variant="secondary" href="{{ route('users.edit', $user) }}">Edit</x-button>
            @if ($user->user_id !== auth()->id())
                <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Delete this user account?')" data-submit-feedback>
                    @csrf
                    @method('DELETE')
                    <x-button icon="delete" variant="danger" type="submit">Delete</x-button>
                </form>
            @endif
        </div>
    </div>
@endsection
