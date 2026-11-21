<?php

namespace App\Services;

use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EarningType;
use App\Models\EmploymentHistory;
use App\Models\EmploymentRecord;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class MasterDataDeletionGuard
{
    /**
     * The caller holds the source row lock. Preserve references that restrict deletion
     * or would otherwise be silently cleared by SET NULL/cascading schedule deletion.
     */
    public function validate(Model $record): void
    {
        $referenced = match (true) {
            $record instanceof Department => $record->positions()->exists() || $record->employmentRecords()->exists()
                || EmploymentHistory::where('from_department_id', $record->getKey())->orWhere('to_department_id', $record->getKey())->exists(),
            $record instanceof Position => $record->employmentRecords()->exists()
                || EmploymentHistory::where('from_position_id', $record->getKey())->orWhere('to_position_id', $record->getKey())->exists(),
            $record instanceof EmploymentRecord => $record->payrollDetails()->exists(),
            $record instanceof SalaryGradeStep => $record->compensationProfiles()->exists(),
            $record instanceof SalarySchedule => $this->scheduleIsReferenced($record),
            $record instanceof EarningType => $record->assignments()->exists() || $record->payrollEarnings()->exists(),
            $record instanceof DeductionType => $record->assignments()->exists() || $record->payrollDeductions()->exists(),
            $record instanceof ContributionRate => $record->employerContributions()->exists(),
            $record instanceof PayrollPeriod => $record->runs()->exists(),
            default => false,
        };

        if ($referenced) {
            $this->reject($record);
        }
    }

    public function reject(Model $record): never
    {
        $message = match (true) {
            $record instanceof Department => 'This department is referenced by positions, appointments or employment history and cannot be deleted. Set its status to Inactive instead.',
            $record instanceof Position => 'This position is referenced by appointments or employment history and cannot be deleted. Set its status to Inactive instead.',
            $record instanceof EmploymentRecord => 'This appointment is referenced by payroll and cannot be deleted. Set its employment status to Inactive instead.',
            $record instanceof SalaryGradeStep => 'This grade step is referenced by compensation profiles and cannot be deleted. Retain it and manage the salary schedule status or effective dates instead.',
            $record instanceof SalarySchedule => 'This salary schedule has grade steps referenced by compensation profiles and cannot be deleted. Set its status to Inactive instead.',
            $record instanceof EarningType => 'This earning type is referenced by assignments or payroll and cannot be deleted. Set its status to Inactive instead.',
            $record instanceof DeductionType => 'This deduction type is referenced by assignments or payroll and cannot be deleted. Set its status to Inactive instead.',
            $record instanceof ContributionRate => 'This contribution rate is referenced by employer payroll contributions and cannot be deleted. Retain it and use effective dates for future rate changes.',
            $record instanceof PayrollPeriod => 'A period with payroll runs cannot be deleted. Retain it and manage its runs through the payroll workflow.',
            default => 'This record is referenced and cannot be deleted. Retain it to preserve related history.',
        };

        throw ValidationException::withMessages(['delete' => $message]);
    }

    public function isReferencedDeleteFailure(QueryException $exception): bool
    {
        if (! str_starts_with(strtolower(ltrim($exception->getSql())), 'delete ')) {
            return false;
        }
        $error = $exception->errorInfo ?? [];

        return ($error[0] ?? null) === '23000'
            && ((int) ($error[1] ?? 0) === 1451
                || (in_array((int) ($error[1] ?? 0), [19, 787], true)
                    && str_contains((string) ($error[2] ?? ''), 'FOREIGN KEY constraint failed')));
    }

    private function scheduleIsReferenced(SalarySchedule $schedule): bool
    {
        $steps = $schedule->gradeSteps()->lockForUpdate()->get(['grade_step_id']);

        return CompensationProfile::whereIn('grade_step_id', $steps->modelKeys())->exists();
    }
}
