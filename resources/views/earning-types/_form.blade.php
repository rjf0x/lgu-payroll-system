@php($isEdit = $earningType->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('earning-types.update', $earningType) : route('earning-types.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"

    data-submit-feedback
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

    <x-audit-change-context />

    <div class="flex flex-wrap gap-3">
        <x-button :icon="$isEdit ? 'save' : 'add'" type="submit" variant="primary">{{ $isEdit ? 'Save changes' : 'Create earning type' }}</x-button>
        <x-button icon="back" href="{{ route('earning-types.index') }}">Cancel</x-button>
    </div>
</form>
