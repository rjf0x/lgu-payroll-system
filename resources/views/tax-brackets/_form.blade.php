@php($isEdit = $bracket->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('tax-brackets.update', $bracket) : route('tax-brackets.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"

    data-submit-feedback
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="tax_type" label="Tax Type" :value="$bracket->tax_type" placeholder="ANNUAL" required />
        <x-form.input name="pay_frequency" label="Pay Frequency" :value="$bracket->pay_frequency" placeholder="ANNUAL" required />
        <x-form.input name="minimum_income" label="Minimum Income" type="number" step="0.01" :value="$bracket->minimum_income" required />
        <x-form.input name="maximum_income" label="Maximum Income" type="number" step="0.01" :value="$bracket->maximum_income" />
        <x-form.input name="base_tax" label="Base Tax" type="number" step="0.01" :value="$bracket->base_tax" required />
        <x-form.input name="marginal_tax_rate" label="Marginal Rate (0–1)" type="number" step="0.0001" :value="$bracket->marginal_tax_rate" required />
        <x-form.input name="effective_from" label="Effective From" type="date" :value="$bracket->effective_from?->toDateString()" required />
        <x-form.input name="effective_to" label="Effective To" type="date" :value="$bracket->effective_to?->toDateString()" />
    </div>

    <div class="flex flex-wrap gap-3">
        <x-button :icon="$isEdit ? 'save' : 'add'" type="submit" variant="primary">{{ $isEdit ? 'Save changes' : 'Create bracket' }}</x-button>
        <x-button icon="back" href="{{ route('tax-brackets.index') }}">Cancel</x-button>
    </div>
</form>
