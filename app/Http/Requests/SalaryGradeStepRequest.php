<?php

namespace App\Http\Requests;

use App\Models\SalaryGradeStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalaryGradeStepRequest extends FormRequest
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
        $gradeStep = $this->route('salary_grade_step');
        $gradeStepId = $gradeStep instanceof SalaryGradeStep ? $gradeStep->grade_step_id : null;
        $scheduleId = $this->input('salary_schedule_id');
        $stepNumber = $this->input('step_number');

        return [
            'salary_schedule_id' => ['required', 'integer', 'exists:salary_schedules,salary_schedule_id'],
            'grade_number' => [
                'required', 'integer', 'between:1,33',
                Rule::unique('salary_grade_steps', 'grade_number')
                    ->where(fn ($query) => $query
                        ->where('salary_schedule_id', $scheduleId)
                        ->where('step_number', $stepNumber))
                    ->ignore($gradeStepId, 'grade_step_id'),
            ],
            'step_number' => ['required', 'integer', 'between:1,8'],
            'monthly_salary' => ['required', 'numeric', 'min:0'],
        ];
    }
}
