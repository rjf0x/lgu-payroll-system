@php($isEdit = $position->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('positions.update', $position) : route('positions.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"

    data-submit-feedback
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.select
            name="department_id"
            label="Department"
            :options="$departments->pluck('department_name', 'department_id')"
            :selected="$position->department_id"
            placeholder="Select a department"
            required
        />
        <x-form.input name="position_code" label="Code" :value="$position->position_code" required />
        <x-form.input name="position_title" label="Title" :value="$position->position_title" required />
        <x-form.input name="salary_grade" label="Salary Grade" type="number" :value="$position->salary_grade" hint="1–33, or blank for non-grade positions" />
    </div>

    <x-form.select
        name="status"
        label="Status"
        :options="['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive']"
        :selected="$position->status"
        required
    />

    <x-form.textarea name="description" label="Description" :value="$position->description" />

    <div class="flex flex-wrap gap-3">
        <button class="ui-button ui-button-primary">
            {{ $isEdit ? 'Save changes' : 'Create position' }}
        </button>
        <a href="{{ route('positions.index') }}" class="ui-button ui-button-secondary">
            Cancel
        </a>
    </div>
</form>
