@php
    $isEdit = $account->exists;
    $selectedRoles = array_map('intval', (array) old('roles', $assignedRoleIds));
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('users.update', $account) : route('users.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="username" label="Username" :value="$account->username" required />
        <x-form.input name="email" label="Email" type="email" :value="$account->email" />
        <x-form.input
            name="password"
            label="{{ $isEdit ? 'New Password (leave blank to keep current)' : 'Password' }}"
            type="password"
            :required="! $isEdit"
        />
        <x-form.select
            name="employee_id"
            label="Linked Employee"
            :options="$employees->mapWithKeys(fn ($e) => [$e->employee_id => $e->employee_number.' — '.$e->full_name])"
            :selected="$account->employee_id"
            placeholder="— none —"
        />
        <x-form.select
            name="account_status"
            label="Account Status"
            :options="['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive']"
            :selected="$account->account_status"
            required
        />
    </div>

    <div>
        <span class="block text-sm font-medium text-slate-700">Roles <span class="text-rose-600">*</span></span>
        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($roles as $role)
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        name="roles[]"
                        value="{{ $role->role_id }}"
                        @checked(in_array($role->role_id, $selectedRoles, true))
                        class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                    >
                    {{ $role->role_name }}
                </label>
            @endforeach
        </div>
        @error('roles')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            {{ $isEdit ? 'Save changes' : 'Create account' }}
        </button>
        <a href="{{ route('users.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
            Cancel
        </a>
    </div>
</form>
