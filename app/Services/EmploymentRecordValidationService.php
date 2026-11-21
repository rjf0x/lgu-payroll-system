<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\Position;
use Illuminate\Validation\ValidationException;

class EmploymentRecordValidationService
{
    /**
     * Check a candidate after field/date validation. Persistence repeats these checks
     * while holding the employee lock, including when no appointments exist yet.
     *
     * @return array<string, string>
     */
    public function issues(EmploymentRecord $record, bool $lock = false): array
    {
        $issues = [];
        $sameEmployee = $record->exists && (int) $record->getRawOriginal('employee_id') === (int) $record->employee_id;
        if ($record->exists && ! $sameEmployee) {
            return ['employee_id' => 'An existing appointment cannot be reassigned to another employee. Create a separate appointment to preserve source and payroll history.'];
        }
        $employee = Employee::withTrashed()->whereKey($record->employee_id)
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if ($employee === null || ($employee->trashed() && (! $sameEmployee || $record->employment_status === EmploymentRecord::STATUS_ACTIVE))) {
            $issues['employee_id'] = 'Archived employees cannot receive new or active appointments. Existing appointments may be made inactive and corrected for history.';
        }

        $position = Position::whereKey($record->position_id)
            ->when($lock, fn ($query) => $query->sharedLock())->first();
        if ($position === null || (int) $position->department_id !== (int) $record->department_id) {
            $issues['position_id'] = 'Select a position that belongs to the selected department.';
        }

        if ($record->employment_status === EmploymentRecord::STATUS_ACTIVE) {
            $overlap = EmploymentRecord::where('employee_id', $record->employee_id)
                ->where('employment_status', EmploymentRecord::STATUS_ACTIVE)
                ->when($record->exists, fn ($query) => $query->where('employment_id', '!=', $record->getKey()))
                ->when($record->end_date !== null, fn ($query) => $query->whereDate('start_date', '<=', $record->end_date))
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $record->start_date))
                ->when($lock, fn ($query) => $query->sharedLock())
                ->first();
            if ($overlap !== null) {
                $issues['start_date'] = 'This employee already has an active appointment covering these dates. Dates are inclusive; end the earlier appointment before the next starts. Concurrent dual appointments are unsupported.';
            }
        }

        return $issues;
    }

    public function validateForMutation(EmploymentRecord $record): void
    {
        $issues = $this->issues($record, lock: true);
        if ($issues !== []) {
            throw ValidationException::withMessages($issues);
        }
    }
}
