@php($isEdit = $rate->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('contribution-rates.update', $rate) : route('contribution-rates.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="contribution_type" label="Contribution Type" :value="$rate->contribution_type" placeholder="GSIS / PhilHealth / Pag-IBIG" required />
        <x-form.input name="minimum_compensation" label="Minimum Compensation" type="number" step="0.01" :value="$rate->minimum_compensation" required />
        <x-form.input name="maximum_compensation" label="Maximum Compensation" type="number" step="0.01" :value="$rate->maximum_compensation" />
        <x-form.input name="employee_rate" label="Employee Rate (0–1)" type="number" step="0.0001" :value="$rate->employee_rate" required />
        <x-form.input name="employer_rate" label="Employer Rate (0–1)" type="number" step="0.0001" :value="$rate->employer_rate" required />
        <x-form.input name="employee_fixed_amount" label="Employee Fixed Amount" type="number" step="0.01" :value="$rate->employee_fixed_amount" />
        <x-form.input name="employer_fixed_amount" label="Employer Fixed Amount" type="number" step="0.01" :value="$rate->employer_fixed_amount" />
        <x-form.input name="effective_from" label="Effective From" type="date" :value="$rate->effective_from?->toDateString()" required />
        <x-form.input name="effective_to" label="Effective To" type="date" :value="$rate->effective_to?->toDateString()" />
    </div>

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">{{ $isEdit ? 'Save changes' : 'Create rate' }}</button>
        <a href="{{ route('contribution-rates.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
    </div>
</form>
