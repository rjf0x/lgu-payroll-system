@php($isEdit = $balance->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('leave-balances.update', $balance) : route('leave-balances.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"

    data-submit-feedback
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.select
            name="employee_id"
            label="Employee"
            :options="$employees->mapWithKeys(fn ($e) => [$e->employee_id => $e->employee_number.' — '.$e->full_name])"
            :selected="$balance->employee_id"
            placeholder="Select an employee"
            required
        />
        <x-form.select
            name="leave_type_id"
            label="Leave Type"
            :options="$leaveTypes->pluck('leave_name', 'leave_type_id')"
            :selected="$balance->leave_type_id"
            placeholder="Select a leave type"
            required
        />
        <x-form.input name="leave_year" label="Year" type="number" :value="$balance->leave_year" required />
        <div class="grid grid-cols-2 gap-5">
            <x-form.input name="opening_balance" label="Opening Balance" type="number" step="0.5" :value="$balance->opening_balance ?? 0" required />
            <x-form.input name="total_credits" label="Total Credits" type="number" step="0.5" :value="$balance->total_credits ?? 0" required />
        </div>
    </div>

    @if ($isEdit)
        <p class="text-xs text-slate-500">
            Total used: {{ number_format((float) $balance->total_used, 2) }} (managed by approved leave applications).
        </p>
    @endif

    <div class="flex flex-wrap gap-3">
        <button class="ui-button ui-button-primary">
            {{ $isEdit ? 'Save changes' : 'Create balance' }}
        </button>
        <a href="{{ route('leave-balances.index') }}" class="ui-button ui-button-secondary">Cancel</a>
    </div>
</form>
