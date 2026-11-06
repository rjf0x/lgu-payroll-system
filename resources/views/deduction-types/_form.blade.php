@php($isEdit = $deductionType->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('deduction-types.update', $deductionType) : route('deduction-types.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="deduction_name" label="Name" :value="$deductionType->deduction_name" required />
        <x-form.input name="deduction_category" label="Category" :value="$deductionType->deduction_category" placeholder="e.g. GSIS_LOAN" required />
        <x-form.select
            name="computation_type"
            label="Computation Type"
            :options="['MANUAL' => 'Manual entry', 'FIXED' => 'Fixed amount', 'PERCENTAGE' => 'Percentage', 'COMPUTED' => 'Computed']"
            :selected="$deductionType->computation_type"
            required
        />
        <x-form.select
            name="is_statutory"
            label="Statutory"
            :options="[1 => 'Yes', 0 => 'No']"
            :selected="$deductionType->is_statutory === null ? null : (int) $deductionType->is_statutory"
            required
        />
        <x-form.select
            name="is_pre_tax"
            label="Pre-tax"
            :options="[1 => 'Yes', 0 => 'No']"
            :selected="$deductionType->is_pre_tax === null ? null : (int) $deductionType->is_pre_tax"
            required
        />
        <x-form.select
            name="status"
            label="Status"
            :options="['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive']"
            :selected="$deductionType->status"
            required
        />
    </div>

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ $isEdit ? 'Save changes' : 'Create deduction type' }}</button>
        <a href="{{ route('deduction-types.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
