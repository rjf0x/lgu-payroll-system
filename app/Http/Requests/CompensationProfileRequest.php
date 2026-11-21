<?php

namespace App\Http\Requests;

use App\Models\CompensationProfile;
use App\Models\EmploymentRecord;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CompensationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `basis` drives the form and the conditional requirements; it is not a column.
            'basis' => ['required', Rule::in(['GRADE_STEP', 'DIRECT_RATE'])],
            'employment_id' => ['required', 'integer', 'exists:employment_records,employment_id'],
            'grade_step_id' => ['nullable', 'integer', 'exists:salary_grade_steps,grade_step_id', 'required_if:basis,GRADE_STEP'],
            'pay_basis' => ['required', Rule::in([
                CompensationProfile::BASIS_MONTHLY,
                CompensationProfile::BASIS_DAILY,
                CompensationProfile::BASIS_PROPORTIONAL_MONTHLY,
            ])],
            'basic_rate' => ['nullable', 'numeric', 'min:0', 'required_if:basis,DIRECT_RATE'],
            'service_fraction' => ['required', 'numeric', 'gt:0', 'lte:1'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['pay_basis.in' => 'Select monthly, daily, or proportional monthly compensation. Hourly payroll requires approved hours and is unsupported.'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateGradeStepMatchesPosition($validator);
            $this->validateScheduleIsEffective($validator);
            $this->validateNoOverlappingPeriod($validator);
        });
    }

    private function validateGradeStepMatchesPosition(Validator $validator): void
    {
        $gradeStepId = $this->input('grade_step_id');

        if (! $gradeStepId) {
            return;
        }

        $step = SalaryGradeStep::find($gradeStepId);
        $positionGrade = EmploymentRecord::with('position')
            ->find($this->input('employment_id'))
            ?->position
            ?->salary_grade;

        if ($step !== null && $positionGrade !== null && (int) $step->grade_number !== (int) $positionGrade) {
            $validator->errors()->add(
                'grade_step_id',
                "The selected grade step (SG {$step->grade_number}) does not match the position's salary grade (SG {$positionGrade}).",
            );
        }
    }

    private function validateScheduleIsEffective(Validator $validator): void
    {
        $gradeStepId = $this->input('grade_step_id');

        if (! $gradeStepId) {
            return;
        }

        $schedule = SalaryGradeStep::with('salarySchedule')->find($gradeStepId)?->salarySchedule;
        $effectiveFrom = $this->date('effective_from');

        if ($schedule === null || $effectiveFrom === null) {
            return;
        }

        if ($schedule->status !== SalarySchedule::STATUS_ACTIVE) {
            $validator->errors()->add('grade_step_id', 'The selected grade step belongs to an inactive salary schedule.');
        }

        if ($effectiveFrom->lt($schedule->effective_from)) {
            $validator->errors()->add('effective_from', 'The effective date is before the salary schedule takes effect.');
        }

        if ($schedule->effective_to !== null && $effectiveFrom->gt($schedule->effective_to)) {
            $validator->errors()->add('effective_from', 'The effective date is after the salary schedule has ended.');
        }
    }

    private function validateNoOverlappingPeriod(Validator $validator): void
    {
        $employmentId = $this->input('employment_id');
        $effectiveFrom = $this->date('effective_from');
        $effectiveTo = $this->date('effective_to');

        if (! $employmentId || $effectiveFrom === null) {
            return;
        }

        $profile = $this->route('compensation_profile');
        $profileId = $profile instanceof CompensationProfile ? $profile->compensation_id : null;

        $overlaps = CompensationProfile::query()
            ->where('employment_id', $employmentId)
            ->when($profileId !== null, fn ($query) => $query->where('compensation_id', '!=', $profileId))
            ->where(function ($query) use ($effectiveFrom): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveFrom);
            })
            ->when($effectiveTo !== null, fn ($query) => $query->where('effective_from', '<=', $effectiveTo))
            ->exists();

        if ($overlaps) {
            $validator->errors()->add(
                'effective_from',
                'This employment already has an active compensation profile covering the selected period.',
            );
        }
    }
}
