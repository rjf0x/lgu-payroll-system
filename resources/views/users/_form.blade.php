@php
    $isEdit = $account->exists;
    $selectedRoles = array_map('intval', (array) old('roles', $assignedRoleIds));
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('users.update', $account) : route('users.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"

    data-submit-feedback
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

    <fieldset aria-invalid="{{ $errors->has('roles') ? 'true' : 'false' }}" aria-describedby="roles-help{{ $errors->has('roles') ? ' roles-error' : '' }}">
        <legend class="text-sm font-semibold">Roles (required)</legend>
        <p id="roles-help" class="text-sm text-muted">Select at least one role. Permissions from multiple roles are combined.</p>
        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($roles as $role)
                <label class="flex min-h-11 items-center gap-2 text-sm text-slate-700">
                    <input
                        type="checkbox"
                        name="roles[]"
                        value="{{ $role->role_id }}"
                        @checked(in_array($role->role_id, $selectedRoles, true))
                        class="rounded border-slate-300 text-brand focus:ring-blue-500"
                    >
                    {{ $role->role_name }}
                </label>
            @endforeach
        </div>
        @error('roles')
            <p id="roles-error" class="mt-1 text-sm text-rose-800">{{ $message }}</p>
        @enderror
    </fieldset>

    <div class="flex flex-wrap gap-3">
        <x-button :icon="$isEdit ? 'save' : 'add'" type="submit" variant="primary">
            {{ $isEdit ? 'Save changes' : 'Create account' }}
        </x-button>
        <x-button icon="back" href="{{ route('users.index') }}">
            Cancel
        </x-button>
    </div>
</form>
