<?php

namespace App\Services;

use App\Enums\PersonnelClassification;
use App\Http\Requests\SaveSalaryScheduleRequest;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\SalaryRate;
use App\Models\SalarySchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalaryService
{
    public function scheduleCandidates(Employee $employee, string $date, bool $demo, bool $lock = false): Collection
    {
        $query = SalarySchedule::where('is_demonstration', $demo)->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereIn('coverage_key', [SalarySchedule::coverageKey($employee->office), SalarySchedule::coverageKey(null)])
                ->orWhere(fn ($legacy) => $legacy->whereNull('coverage_key')->where('personnel_classification', $employee->personnel_classification)))
            ->orderByDesc('effective_from')->orderByDesc('id');

        return ($lock ? $query->lockForUpdate() : $query)->get()->whereNotNull('published_at');
    }

    public function resolveSchedule(Employee $employee, Collection $schedules, string $date): ?SalarySchedule
    {
        foreach ([SalarySchedule::coverageKey($employee->office), SalarySchedule::coverageKey(null), null] as $key) {
            $match = $schedules->first(fn ($schedule) => $schedule->coverage_key === $key && $schedule->effective_from->format('Y-m-d') <= $date);
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    public function applicableSchedule(Employee|PersonnelClassification $employee, string $date, bool $demo): ?SalarySchedule
    {
        if ($employee instanceof PersonnelClassification) {
            // Compatibility for historical classification-only lookups; employee workflows use department coverage.
            return SalarySchedule::whereNull('coverage_key')->where('personnel_classification', $employee)->where('is_demonstration', $demo)
                ->whereNotNull('published_at')->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->first();
        }

        return $this->resolveSchedule($employee, $this->scheduleCandidates($employee, $date, $demo), $date);
    }

    public function salaryAsOf(Employee $employee, string $date): ?EmployeeSalaryRecord
    {
        return EmployeeSalaryRecord::with('rate.schedule')->where('employee_id', $employee->id)
            ->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->first();
    }

    public function stepReview(?EmployeeSalaryRecord $record, string $date): ?array
    {
        if (! $record) {
            return null;
        }
        $reviewDate = CarbonImmutable::parse($record->step_basis_date)->addYearsNoOverflow(3)->format('Y-m-d');

        return ['date' => $reviewDate, 'due' => CarbonImmutable::parse($date)->greaterThanOrEqualTo(CarbonImmutable::parse($record->step_basis_date)->addYearsNoOverflow(3))];
    }

    private function actor(Request $request): User
    {
        $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);

        return $actor;
    }

    private function draft(SalarySchedule $schedule): void
    {
        if ($schedule->published_at !== null) {
            throw ValidationException::withMessages(['schedule' => 'Published schedules are locked. Create a new effective-dated schedule.']);
        }
    }

    public function saveSchedule(Request $request, array $data, ?SalarySchedule $schedule = null): SalarySchedule
    {
        try {
            return DB::transaction(function () use ($request, $data, $schedule) {
                $actor = $this->actor($request);
                Gate::forUser($actor)->authorize($schedule ? 'update' : 'create', $schedule ?? SalarySchedule::class);
                $current = $schedule ? SalarySchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail() : new SalarySchedule;
                $this->draft($current);
                $data = Validator::make($data, (new SaveSalaryScheduleRequest)->rules())->validate();
                $department = $data['department_scope'] === 'all' ? null : trim(substr($data['department_scope'], strlen('department:')));
                unset($data['department_scope']);
                $current->fill($data);
                $current->department = $department;
                $current->coverage_key = SalarySchedule::coverageKey($department);
                $current->personnel_classification = null;
                if (! $current->exists) {
                    $current->created_by = $actor->id;
                }
                $current->save();

                return $current;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['effective_from' => 'A schedule already exists for this department coverage, date, and data use.']);
        }
    }

    public function saveRate(Request $request, SalarySchedule $schedule, array $data, ?SalaryRate $rate = null): void
    {
        try {
            DB::transaction(function () use ($request, $schedule, $data, $rate) {
                Gate::forUser($this->actor($request))->authorize('update', $schedule);
                $current = SalarySchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
                $this->draft($current);
                $row = $rate ? $current->rates()->whereKey($rate->id)->lockForUpdate()->firstOrFail() : new SalaryRate;
                $row->fill($data);
                $row->monthly_salary = bcadd($data['monthly_salary'], '0', 2);
                $current->rates()->save($row);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['grade' => 'This grade and step already exist in the schedule.']);
        }
    }

    public function publish(Request $request, SalarySchedule $schedule, string $reference): void
    {
        DB::transaction(function () use ($request, $schedule, $reference) {
            Gate::forUser($this->actor($request))->authorize('update', $schedule);
            $current = SalarySchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            $this->draft($current);
            if (! $current->rates()->exists()) {
                throw ValidationException::withMessages(['schedule' => 'Enter at least one authorized grade/step rate before publishing.']);
            }
            $current->authority_reference = $reference;
            $current->published_at = now();
            $current->save();
        }, 3);
    }

    public function recordSalary(Request $request, Employee $employee, array $data): EmployeeSalaryRecord
    {
        try {
            return DB::transaction(function () use ($request, $employee, $data) {
                $actor = $this->actor($request);
                $current = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $current);
                if (! $current->is_active) {
                    throw ValidationException::withMessages(['employee' => 'An inactive employee cannot receive a new salary assignment.']);
                }
                $rate = SalaryRate::findOrFail($data['salary_rate_id']);
                $schedule = SalarySchedule::whereKey($rate->salary_schedule_id)->lockForUpdate()->firstOrFail();
                $rate = $schedule->rates()->whereKey($rate->id)->lockForUpdate()->firstOrFail();
                $applicable = $this->applicableSchedule($current, $data['effective_from'], $schedule->is_demonstration);
                if (! $applicable || $applicable->id !== $schedule->id) {
                    throw ValidationException::withMessages(['salary_rate_id' => 'Select a rate from the published schedule applicable to this department and effective date.']);
                }
                $previous = EmployeeSalaryRecord::with('rate')->where('employee_id', $current->id)->orderByDesc('effective_from')->first();
                if ($data['effective_from'] < $current->employment_start_date->format('Y-m-d')
                    || ($previous && ($data['effective_from'] <= $previous->effective_from->format('Y-m-d') || $data['effective_from'] < today()->format('Y-m-d')))) {
                    throw ValidationException::withMessages(['effective_from' => 'Use a date on or after employment began; subsequent changes must be today or later and after the last recorded change.']);
                }
                $type = $data['change_type'];
                $sameGrade = $previous && $previous->rate->grade === $rate->grade;
                $sameStep = $previous && $previous->rate->step === $rate->step;
                if ((! $previous && $type !== 'initial') || ($previous && $type === 'initial')
                    || ($type === 'step_increment' && (! $sameGrade || $rate->step <= $previous->rate->step))
                    || ($type === 'promotion' && $sameGrade)
                    || ($type === 'schedule_update' && (! $sameGrade || ! $sameStep || $previous->rate->salary_schedule_id === $schedule->id))) {
                    throw ValidationException::withMessages(['change_type' => 'Initial assignments, same-grade step increases, different-grade promotions, and same-grade/step schedule updates must be recorded distinctly.']);
                }
                $basis = $type === 'schedule_update' ? $previous->step_basis_date->format('Y-m-d') : $data['step_basis_date'];
                if ($basis > $data['effective_from'] || $basis < $current->employment_start_date->format('Y-m-d')) {
                    throw ValidationException::withMessages(['step_basis_date' => 'The review basis must be between employment start and this change date.']);
                }
                $record = new EmployeeSalaryRecord;
                $record->forceFill([
                    'employee_id' => $current->id, 'salary_rate_id' => $rate->id,
                    'effective_from' => $data['effective_from'], 'change_type' => $type,
                    'personnel_classification' => $current->personnel_classification->value, 'position' => $data['position'],
                    'monthly_salary' => $rate->monthly_salary, 'is_demonstration' => $schedule->is_demonstration,
                    'step_basis_date' => $basis, 'authority_reference' => $data['authority_reference'],
                    'reason' => $data['reason'], 'created_by' => $actor->id,
                ])->save();

                // Salary history keeps the authorized position as a snapshot. The
                // editable employee profile is not rewritten for future-dated changes.
                return $record;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['effective_from' => 'A salary change already exists for this employee and effective date.']);
        }
    }
}
