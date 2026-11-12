@php($isEdit = $period->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('payroll-periods.update', $period) : route('payroll-periods.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"

    data-submit-feedback
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="period_name" label="Period Name" :value="$period->period_name" placeholder="e.g. January 2025" required />
        <x-form.select name="payroll_type" label="Payroll Type" :options="['MONTHLY' => 'Monthly']" :selected="$period->payroll_type" required />
        <x-form.input name="start_date" label="Start Date" type="date" :value="$period->start_date?->toDateString()" required />
        <x-form.input name="end_date" label="End Date" type="date" :value="$period->end_date?->toDateString()" required />
        <x-form.input name="pay_date" label="Pay Date" type="date" :value="$period->pay_date?->toDateString()" required />
        <x-form.select name="status" label="Status" :options="['OPEN' => 'Open', 'CLOSED' => 'Closed']" :selected="$period->status" required />
    </div>

    <div class="flex flex-wrap gap-3">
        <x-button :icon="$isEdit ? 'save' : 'add'" type="submit" variant="primary">{{ $isEdit ? 'Save changes' : 'Create period' }}</x-button>
        <x-button icon="back" href="{{ route('payroll-periods.index') }}">Cancel</x-button>
    </div>
</form>
