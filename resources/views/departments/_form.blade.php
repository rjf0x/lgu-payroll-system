@php($isEdit = $department->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('departments.update', $department) : route('departments.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"

    data-submit-feedback
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="department_code" label="Code" :value="$department->department_code" required />
        <x-form.input name="department_name" label="Name" :value="$department->department_name" required />
    </div>

    <x-form.select
        name="status"
        label="Status"
        :options="['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive']"
        :selected="$department->status"
        required
    />

    <x-form.textarea name="description" label="Description" :value="$department->description" />

    <x-audit-change-context />

    <div class="flex flex-wrap gap-3">
        <x-button :icon="$isEdit ? 'save' : 'add'" type="submit" variant="primary">
            {{ $isEdit ? 'Save changes' : 'Create department' }}
        </x-button>
        <x-button icon="back" href="{{ route('departments.index') }}">
            Cancel
        </x-button>
    </div>
</form>
