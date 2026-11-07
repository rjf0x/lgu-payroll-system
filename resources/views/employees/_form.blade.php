@php($isEdit = $employee->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('employees.update', $employee) : route('employees.store') }}"
    class="ui-card space-y-6"
    data-submit-feedback
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="employee_number" label="Employee Number" :value="$employee->employee_number" required />
        <x-form.input name="birth_date" label="Birth Date" type="date" :value="$employee->birth_date?->toDateString()" required />
        <x-form.input name="first_name" label="First Name" :value="$employee->first_name" required />
        <x-form.input name="middle_name" label="Middle Name" :value="$employee->middle_name" />
        <x-form.input name="last_name" label="Last Name" :value="$employee->last_name" required />
        <x-form.input name="suffix" label="Suffix" :value="$employee->suffix" />
        <x-form.select
            name="sex"
            label="Sex"
            :options="['MALE' => 'Male', 'FEMALE' => 'Female']"
            :selected="$employee->sex"
            placeholder="Select"
            required
        />
        <x-form.select
            name="civil_status"
            label="Civil Status"
            :options="['SINGLE' => 'Single', 'MARRIED' => 'Married', 'WIDOWED' => 'Widowed', 'SEPARATED' => 'Separated']"
            :selected="$employee->civil_status"
            placeholder="—"
        />
        <x-form.input name="email" label="Email" type="email" :value="$employee->email" />
        <x-form.input name="contact_number" label="Contact Number" :value="$employee->contact_number" />
    </div>

    <x-form.textarea name="address" label="Address" :value="$employee->address" required />

    <p class="text-sm text-muted">Fields marked with an asterisk (*) are required.</p>
    <div class="flex flex-wrap gap-3">
        <x-button type="submit" variant="primary" data-submitting-label="Saving employee…">
            {{ $isEdit ? 'Save changes' : 'Create employee' }}
        </x-button>
        <x-button :href="$isEdit ? route('employees.show', $employee) : route('employees.index')">
            Cancel
        </x-button>
    </div>
</form>
