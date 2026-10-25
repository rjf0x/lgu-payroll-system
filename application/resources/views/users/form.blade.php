@extends('layouts.app')
@section('content')
    @php
        $oldName = old('name', $account->name);
        $oldEmail = old('email', $account->email);
        $oldRole = old('role', $account->role?->value ?? 'viewer');
        $oldStatus = old('is_active', (int) $account->is_active);
        $roleValue = is_string($oldRole) && \App\Enums\Role::tryFrom($oldRole) ? $oldRole : ($account->role?->value ?? 'viewer');
        $statusValue = is_scalar($oldStatus) ? (string) $oldStatus : (string) (int) $account->is_active;
    @endphp
    <h1 class="text-3xl font-bold">{{ $account->exists ? 'Edit account' : 'Create account' }}</h1>
    <form method="POST" action="{{ $account->exists ? route('users.update', $account) : route('users.store') }}" class="mt-6 max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($account->exists) @method('PUT') @endif
        <div>
            <label for="name" class="block text-sm font-semibold">Name</label>
            <input id="name" name="name" value="{{ is_string($oldName) ? $oldName : '' }}" required maxlength="255" autocomplete="name" class="mt-2 w-full rounded border border-slate-300 p-3">
        </div>
        <div>
            <label for="email" class="block text-sm font-semibold">Email</label>
            <input id="email" name="email" type="email" value="{{ is_string($oldEmail) ? $oldEmail : '' }}" required maxlength="255" autocomplete="off" class="mt-2 w-full rounded border border-slate-300 p-3">
        </div>
        <div>
            <label for="role" class="block text-sm font-semibold">Role</label>
            <select id="role" name="role" required class="mt-2 w-full rounded border border-slate-300 p-3">
                @foreach ($roles as $option)
                    <option value="{{ $option->value }}" @selected($roleValue === $option->value)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="is_active" class="block text-sm font-semibold">Account status</label>
            <select id="is_active" name="is_active" class="mt-2 w-full rounded border border-slate-300 p-3">
                <option value="1" @selected($statusValue === '1')>Active</option>
                <option value="0" @selected($statusValue === '0')>Inactive</option>
            </select>
            @if ($account->is(auth()->user()))<p class="mt-2 text-sm text-slate-600">Your own account must remain an active administrator.</p>@endif
        </div>
        <div>
            <label for="password" class="block text-sm font-semibold">{{ $account->exists ? 'New password (leave blank to keep current)' : 'Password' }}</label>
            <p id="password-help" class="mt-1 text-sm text-slate-600">At least 12 characters, including uppercase, lowercase and numbers. Maximum 72 bytes; some characters use more than one byte.</p>
            <input id="password" name="password" type="password" @required(! $account->exists) maxlength="72" autocomplete="new-password" aria-describedby="password-help" class="mt-2 w-full rounded border border-slate-300 p-3">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" @required(! $account->exists) maxlength="72" autocomplete="new-password" class="mt-2 w-full rounded border border-slate-300 p-3">
        </div>
        <div class="flex items-center gap-5">
            <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Save account</button>
            <a href="{{ route('users.index') }}" class="text-teal-800 underline">Cancel</a>
        </div>
    </form>
@endsection
