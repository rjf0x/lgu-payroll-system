@php
    $isEdit = $profile->exists;
    $payBases = ['MONTHLY' => 'Monthly', 'DAILY' => 'Daily', 'PROPORTIONAL_MONTHLY' => 'Proportional monthly'];
    if ($isEdit && ! array_key_exists($profile->pay_basis, $payBases)) {
        $payBases[$profile->pay_basis] = $profile->pay_basis.' (unsupported; select a supported pay basis)';
    }
@endphp

<form
    method="POST"
    action="{{ $isEdit ? route('compensation-profiles.update', $profile) : route('compensation-profiles.store') }}"
    class="ui-card space-y-5" data-submit-feedback
>
    @csrf
    <x-employee-context :employee="$workspaceEmployee ?? null" section="compensation" />
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.select
            name="employment_id"
            label="Employment Record"
            :options="$employments->mapWithKeys(fn ($e) => [$e->employment_id => $e->employee->employee_number.' — '.$e->employee->full_name.' ('.$e->position->position_title.')'])"
            :selected="$profile->employment_id"
            placeholder="Select an employment record"
            required
        />
        <x-form.select
            name="basis"
            label="Compensation Basis"
            :options="['GRADE_STEP' => 'Salary grade & step', 'DIRECT_RATE' => 'Direct rate']"
            :selected="$profile->basis ?? 'GRADE_STEP'"
            required
        />
    </div>

    <div id="grade_step_wrap">
        <x-form.select
            name="grade_step_id"
            label="Grade Step"
            :options="$gradeSteps->mapWithKeys(fn ($s) => [$s->grade_step_id => $s->salarySchedule->schedule_name.' · SG '.$s->grade_number.' Step '.$s->step_number.' = ₱'.number_format((float) $s->monthly_salary, 2)])"
            :selected="$profile->grade_step_id"
            placeholder="Select a grade step"
        />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.input name="basic_rate" label="Basic Rate" type="number" step="0.01" :value="$profile->basic_rate" />
        <x-form.input name="service_fraction" label="Service Fraction" type="number" step="0.0001" :value="$profile->service_fraction ?? '1.0000'" required />
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <x-form.select
            name="pay_basis"
            label="Pay Basis"
            :options="$payBases"
            :selected="$profile->pay_basis"
            required
        />
        <div class="grid grid-cols-2 gap-5">
            <x-form.input name="effective_from" label="Effective From" type="date" :value="$profile->effective_from?->toDateString()" required />
            <x-form.input name="effective_to" label="Effective To" type="date" :value="$profile->effective_to?->toDateString()" />
        </div>
    </div>

    <x-audit-change-context />

    <div class="flex flex-wrap gap-3">
        <x-button :icon="$isEdit ? 'save' : 'add'" type="submit" variant="primary">
            {{ $isEdit ? 'Save changes' : 'Create compensation profile' }}
        </x-button>
        <x-button icon="back" href="{{ $workspaceEmployee ? route('employees.show', $workspaceEmployee).'#compensation' : route('compensation-profiles.index') }}">
            Cancel
        </x-button>
    </div>
</form>

<script>
    (function () {
        const basis = document.getElementById('basis');
        const gradeWrap = document.getElementById('grade_step_wrap');
        const rateInput = document.getElementById('basic_rate');

        if (! basis || ! gradeWrap) {
            return;
        }

        function sync() {
            const isGrade = basis.value === 'GRADE_STEP';
            gradeWrap.style.display = isGrade ? '' : 'none';

            if (rateInput) {
                rateInput.closest('div').style.display = isGrade ? 'none' : '';
            }
        }

        basis.addEventListener('change', sync);
        sync();
    })();
</script>
