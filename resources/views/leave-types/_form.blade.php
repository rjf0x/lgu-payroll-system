@php($isEdit = $leaveType->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('leave-types.update', $leaveType) : route('leave-types.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="leave_name" label="Leave Name" :value="$leaveType->leave_name" required />
        <x-form.input name="default_credit" label="Default Credit" type="number" step="0.5" :value="$leaveType->default_credit" required />
        <x-form.select
            name="is_paid"
            label="Paid Leave"
            :options="[1 => 'Paid', 0 => 'Unpaid']"
            :selected="$leaveType->is_paid === null ? null : (int) $leaveType->is_paid"
            required
        />
        <x-form.select
            name="status"
            label="Status"
            :options="['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive']"
            :selected="$leaveType->status"
            required
        />
    </div>

    <x-form.textarea name="description" label="Description" :value="$leaveType->description" />

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            {{ $isEdit ? 'Save changes' : 'Create leave type' }}
        </button>
        <a href="{{ route('leave-types.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
