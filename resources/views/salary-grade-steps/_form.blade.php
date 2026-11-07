@php($isEdit = $gradeStep->exists)

<form
    method="POST"
    action="{{ $isEdit ? route('salary-grade-steps.update', $gradeStep) : route('salary-grade-steps.store') }}"
    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"

    data-submit-feedback
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.select
        name="salary_schedule_id"
        label="Salary Schedule"
        :options="$schedules->pluck('schedule_name', 'salary_schedule_id')"
        :selected="$gradeStep->salary_schedule_id"
        placeholder="Select a schedule"
        required
    />

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
        <x-form.input name="grade_number" label="Grade Number" type="number" :value="$gradeStep->grade_number" required />
        <x-form.input name="step_number" label="Step Number" type="number" :value="$gradeStep->step_number" required />
        <x-form.input name="monthly_salary" label="Monthly Salary" type="number" step="0.01" :value="$gradeStep->monthly_salary" required />
    </div>

    <div class="flex flex-wrap gap-3">
        <button class="ui-button ui-button-primary">
            {{ $isEdit ? 'Save changes' : 'Create grade step' }}
        </button>
        <a href="{{ route('salary-grade-steps.index') }}" class="ui-button ui-button-secondary">
            Cancel
        </a>
    </div>
</form>
