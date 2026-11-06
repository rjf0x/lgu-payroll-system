@php($isEdit = $department->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('departments.update', $department) : route('departments.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
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

    <div class="flex gap-3">
        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">
            {{ $isEdit ? 'Save changes' : 'Create department' }}
        </button>
        <a href="{{ route('departments.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">
            Cancel
        </a>
    </div>
</form>
