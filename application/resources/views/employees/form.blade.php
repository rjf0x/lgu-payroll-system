@extends('layouts.app')
@section('title', 'Employee record · LGU Payroll')
@section('content')
    @php
        $oldText = function ($field, $default = '') {
            $value = old($field, $default);
            return is_string($value) ? $value : '';
        };
        $oldClassification = $oldText('personnel_classification', $employee->personnel_classification?->value ?? '');
        $oldStatus = old('is_active', (int) $employee->is_active);
        $statusValue = is_scalar($oldStatus) ? (string) $oldStatus : (string) (int) $employee->is_active;
        $oldVersion = old('record_version', $employee->record_version);
        $versionValue = is_scalar($oldVersion) && ctype_digit((string) $oldVersion) ? $oldVersion : $employee->record_version;
    @endphp
    <a href="{{ route('employees.index') }}" class="text-teal-800 underline">Back to employees</a>
    <h1 class="mt-5 text-3xl font-bold">{{ $employee->exists ? 'Edit employee' : 'Create employee' }}</h1>
    @if ($employee->exists)
        <a href="{{ route('employees.edit', $employee) }}" class="mt-3 inline-block text-sm text-teal-800 underline">Reload saved record</a>
    @endif
    <form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="mt-6 max-w-3xl space-y-6 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($employee->exists)
            @method('PUT')
            <input type="hidden" name="record_version" value="{{ $versionValue }}">
        @endif
        <div>
            <label for="employee_number" class="block text-sm font-semibold">Employee number</label>
            <p id="number-help" class="mt-1 text-sm text-slate-600">Unique number, up to 50 characters. Letters, numbers, dots, underscores, slashes and hyphens are accepted.</p>
            <input id="employee_number" name="employee_number" value="{{ $oldText('employee_number', $employee->employee_number) }}" required maxlength="50" aria-describedby="number-help" class="mt-2 w-full rounded border border-slate-300 p-3">
            <x-ui.field-error field="employee_number" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            @foreach (['first_name' => 'First name', 'middle_name' => 'Middle name (optional)', 'last_name' => 'Last name', 'suffix' => 'Suffix (optional)'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="block text-sm font-semibold">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" value="{{ $oldText($field, $employee->$field) }}" @required(in_array($field, ['first_name', 'last_name'])) maxlength="{{ $field === 'suffix' ? 20 : 100 }}" class="mt-2 w-full rounded border border-slate-300 p-3">
                    <x-ui.field-error field="{{ $field }}" />
                </div>
            @endforeach
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            @foreach (['position' => 'Position', 'office' => 'Office / department'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="block text-sm font-semibold">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" value="{{ $oldText($field, $employee->$field) }}" required maxlength="150" class="mt-2 w-full rounded border border-slate-300 p-3">
                    <x-ui.field-error field="{{ $field }}" />
                </div>
            @endforeach
        </div>
        <div>
            <label for="personnel_classification" class="block text-sm font-semibold">Personnel classification</label>
            <select id="personnel_classification" name="personnel_classification" required class="mt-2 w-full rounded border border-slate-300 p-3">
                <option value="">Select a classification</option>
                @foreach ($classifications as $classification)
                    <option value="{{ $classification->value }}" @selected($oldClassification === $classification->value)>{{ $classification->label() }}</option>
                @endforeach
            </select>
            <x-ui.field-error field="personnel_classification" />
        </div>
        <div>
            <label for="employment_start_date" class="block text-sm font-semibold">Employment start date</label>
            <input id="employment_start_date" name="employment_start_date" type="date" value="{{ $oldText('employment_start_date', $employee->employment_start_date?->format('Y-m-d')) }}" min="1000-01-01" max="9999-12-31" required class="mt-2 w-full rounded border border-slate-300 p-3">
            <x-ui.field-error field="employment_start_date" />
        </div>
        <div>
            <label for="is_active" class="block text-sm font-semibold">Record status</label>
            <select id="is_active" name="is_active" required class="mt-2 w-full rounded border border-slate-300 p-3">
                <option value="1" @selected($statusValue === '1')>Active</option>
                <option value="0" @selected($statusValue === '0')>Inactive</option>
            </select>
            <x-ui.field-error field="is_active" />
        </div>
        <div class="flex items-center gap-5">
            <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Save employee</button>
            <a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="text-teal-800 underline">Cancel</a>
        </div>
    </form>
@endsection
