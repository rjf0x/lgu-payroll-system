<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollEntry;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayrollPreparationService
{
    private function actor(Request $request): User
    {
        $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);

        return $actor;
    }

    public function create(Request $request, array $data): PayrollBatch
    {
        try {
            return DB::transaction(function () use ($request, $data) {
                $actor = $this->actor($request);
                Gate::forUser($actor)->authorize('create', PayrollBatch::class);

                return PayrollBatch::forceCreate([
                    'period_start' => $data['period'].'-01', 'is_demonstration' => $data['is_demonstration'],
                    'payroll_version' => 1,
                    'status' => 'draft', 'record_version' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id,
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['period' => 'A batch already exists for this month and data use. Reopen the existing draft.']);
        }
    }

    /** Resolve a whole-month source without guessing mid-month compensation rules. */
    public function source(Employee $employee, PayrollBatch $batch, bool $lock = false): array
    {
        $start = $batch->period_start->format('Y-m-d');
        $end = $batch->period_start->copy()->endOfMonth()->format('Y-m-d');
        if (! $employee->is_active || $employee->employment_start_date->format('Y-m-d') > $start) {
            return ['record' => null, 'issue' => 'Employee must be active and employed by the first day of the month.'];
        }
        $salary = app(SalaryService::class);
        $schedules = $salary->scheduleCandidates($employee, $end, $batch->is_demonstration, $lock);
        $applicable = $salary->resolveSchedule($employee, $schedules, $start);
        $recordsQuery = EmployeeSalaryRecord::with('rate.schedule')->where('employee_id', $employee->id)
            ->whereDate('effective_from', '<=', $end)->orderByDesc('effective_from');
        $records = ($lock ? $recordsQuery->lockForUpdate() : $recordsQuery)->get();
        $record = $records->first(fn ($row) => $row->effective_from->format('Y-m-d') <= $start);
        if (! $record) {
            return ['record' => null, 'issue' => 'No authorized salary is effective on the first day of the month.'];
        }
        if ($record->effective_from->format('Y-m-d') < $employee->employment_start_date->format('Y-m-d')) {
            return ['record' => null, 'issue' => 'Salary predates the current employment start date. Review the employee and salary records.'];
        }
        if ($record->is_demonstration !== $batch->is_demonstration || $record->personnel_classification !== $employee->personnel_classification) {
            return ['record' => null, 'issue' => 'The latest salary has a different data use or personnel classification. Review salary history.'];
        }
        if (! $applicable || $record->rate->salary_schedule_id !== $applicable->id
            || bccomp($record->monthly_salary, $record->rate->monthly_salary, 2) !== 0) {
            return ['record' => null, 'issue' => 'Salary source is missing or superseded. Record an authorized applicable salary update first.'];
        }
        if ($records->contains(fn ($row) => $row->effective_from->format('Y-m-d') > $start)
            || $schedules->contains(fn ($schedule) => $schedule->effective_from->format('Y-m-d') > $start
                && $salary->resolveSchedule($employee, $schedules, $schedule->effective_from->format('Y-m-d'))?->id !== $applicable->id)) {
            return ['record' => null, 'issue' => 'A salary or schedule change occurs within this month. Mid-month treatment requires validated rules.'];
        }

        return ['record' => $record, 'issue' => null];
    }

    public function snapshot(Employee $employee, EmployeeSalaryRecord $record): array
    {
        return [
            'employee_id' => $employee->id, 'employee_salary_record_id' => $record->id,
            'employee_number' => $employee->employee_number, 'employee_name' => $employee->full_name,
            'office' => $employee->office, 'position' => $record->position,
            'personnel_classification' => $record->personnel_classification->value,
            'grade' => $record->rate->grade, 'step' => $record->rate->step, 'monthly_salary' => $record->monthly_salary,
        ];
    }

    public function entryIssue(PayrollEntry $entry, PayrollBatch $batch): ?string
    {
        $source = $this->source($entry->employee, $batch);
        if ($source['issue']) {
            return $source['issue'];
        }
        foreach ($this->snapshot($entry->employee, $source['record']) as $field => $value) {
            $saved = $field === 'monthly_salary' ? $entry->monthly_salary : $entry->getRawOriginal($field);
            if ((string) $saved !== (string) $value) {
                return 'Employee or salary details changed after this draft entry was saved. Remove and re-add to refresh.';
            }
        }

        return null;
    }

    private function editable(Request $request, PayrollBatch $batch, int $version): PayrollBatch
    {
        $actor = $this->actor($request);
        $current = PayrollBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor)->authorize('update', $current);
        if ($current->record_version !== $version) {
            throw ValidationException::withMessages(['record_version' => 'This draft changed in another session. Reload it before saving.']);
        }
        $current->updated_by = $actor->id;
        $current->coverage_preparer_ids = array_values(array_unique([...($current->coverage_preparer_ids ?? []), $actor->id]));

        return $current;
    }

    public function add(Request $request, PayrollBatch $batch, array $data): void
    {
        try {
            DB::transaction(function () use ($request, $batch, $data) {
                $current = $this->editable($request, $batch, (int) $data['record_version']);
                $employees = Employee::whereIn('id', $data['employee_ids'])->orderBy('id')->lockForUpdate()->get();
                if ($employees->count() !== count($data['employee_ids'])) {
                    throw ValidationException::withMessages(['employee_ids' => 'Reload the employee list and select existing employees once each.']);
                }
                foreach ($employees as $employee) {
                    if ($current->entries()->where('employee_id', $employee->id)->exists()) {
                        throw ValidationException::withMessages(['employee_ids' => 'An employee is already covered. Reload the draft.']);
                    }
                    $source = $this->source($employee, $current, true);
                    if ($source['issue']) {
                        throw ValidationException::withMessages(['employee_ids' => $employee->employee_number.': '.$source['issue']]);
                    }
                    $current->entries()->save((new PayrollEntry)->forceFill($this->snapshot($employee, $source['record'])));
                }
                $current->record_version++;
                $current->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_ids' => 'An employee is already covered. Reload the draft.']);
        }
    }

    public function remove(Request $request, PayrollBatch $batch, PayrollEntry $entry, int $version): void
    {
        DB::transaction(function () use ($request, $batch, $entry, $version) {
            $current = $this->editable($request, $batch, $version);
            $current->entries()->whereKey($entry->id)->firstOrFail()->delete();
            $current->record_version++;
            $current->save();
        }, 3);
    }
}
