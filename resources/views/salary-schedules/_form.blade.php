@php($isEdit = $schedule->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('salary-schedules.update', $schedule) : route('salary-schedules.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.input name="schedule_name" label="Schedule Name" :value="$schedule->schedule_name" required />

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="effective_from" label="Effective From" type="date" :value="$schedule->effective_from?->toDateString()" required />
        <x-form.input name="effective_to" label="Effective To" type="date" :value="$schedule->effective_to?->toDateString()" />
        <x-form.input name="reference_document" label="Reference Document" :value="$schedule->reference_document" />
        <x-form.select
            name="status"
            label="Status"
            :options="['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive']"
            :selected="$schedule->status"
            required
        />
    </div>

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            {{ $isEdit ? 'Save changes' : 'Create schedule' }}
        </button>
        <a href="{{ route('salary-schedules.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
            Cancel
        </a>
    </div>
</form>
