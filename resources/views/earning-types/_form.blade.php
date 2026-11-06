@php($isEdit = $earningType->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('earning-types.update', $earningType) : route('earning-types.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="earning_name" label="Name" :value="$earningType->earning_name" required />
        <x-form.input name="earning_category" label="Category" :value="$earningType->earning_category" placeholder="ALLOWANCE" required />
        <x-form.select
            name="computation_type"
            label="Computation Type"
            :options="['MANUAL' => 'Manual entry', 'FIXED' => 'Fixed amount', 'PERCENTAGE' => 'Percentage']"
            :selected="$earningType->computation_type"
            required
        />
        <x-form.select
            name="is_taxable"
            label="Taxable"
            :options="[1 => 'Yes', 0 => 'No']"
            :selected="$earningType->is_taxable === null ? null : (int) $earningType->is_taxable"
            required
        />
        <x-form.select
            name="is_recurring"
            label="Recurring"
            :options="[1 => 'Yes', 0 => 'No']"
            :selected="$earningType->is_recurring === null ? null : (int) $earningType->is_recurring"
            required
        />
        <x-form.select
            name="status"
            label="Status"
            :options="['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive']"
            :selected="$earningType->status"
            required
        />
    </div>

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ $isEdit ? 'Save changes' : 'Create earning type' }}</button>
        <a href="{{ route('earning-types.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
