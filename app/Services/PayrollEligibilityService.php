<?php

namespace App\Services;

use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollEmployerContribution;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollEligibilityService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function overlaps(EmploymentRecord $employment, PayrollPeriod $period): bool
    {
        return $employment->start_date->lte($period->end_date)
            && ($employment->end_date === null || $employment->end_date->gte($period->start_date))
            && ($employment->end_date === null || $employment->end_date->gte($employment->start_date));
    }

    public function requiresException(EmploymentRecord $employment): bool
    {
        return $employment->employee?->trashed() !== false
            || $employment->employment_status !== EmploymentRecord::STATUS_ACTIVE;
    }

    /** @return array<string, int|string|null> */
    public function context(EmploymentRecord $employment, PayrollPeriod $period): array
    {
        return [
            'employee_id' => $employment->employee_id,
            'employment_id' => $employment->getKey(),
            'archived_at' => $employment->employee?->deleted_at?->toISOString(),
            'employment_status' => $employment->employment_status,
            'start_date' => $employment->start_date->toDateString(),
            'end_date' => $employment->end_date?->toDateString(),
            'coverage_start' => $period->start_date->toDateString(),
            'coverage_end' => $period->end_date->toDateString(),
        ];
    }

    public function issue(PayrollDetail $detail, PayrollPeriod $period): ?string
    {
        $employment = $detail->employmentRecord;
        if ($employment === null || $employment->employee === null || $employment->employee_id !== $detail->employee_id) {
            return 'Employee and employment records must match.';
        }
        if (! $this->overlaps($employment, $period)) {
            return 'Employment dates do not overlap payroll coverage. Remove this employee from the run.';
        }
        if ($this->requiresException($employment) && (
            blank($detail->eligibility_reason) || $detail->eligibility_resolved_by === null
            || $detail->eligibility_resolved_at === null
            || $detail->eligibility_context != $this->context($employment, $period)
        )) {
            return 'Archived or inactive employee: confirm payable compensation with a documented eligibility justification, or remove the employee.';
        }

        return null;
    }

    public function resolve(PayrollDetail $detail, User $user, string $reason): void
    {
        DB::transaction(function () use ($detail, $user, $reason): void {
            $run = PayrollRun::whereKey($detail->payroll_run_id)->lockForUpdate()->firstOrFail();
            abort_unless($run->isEditable(), 403, 'Return this payroll for correction before changing employee inclusion.');
            $detail->refresh();
            $detail->load('employmentRecord.employee');
            $employment = $detail->employmentRecord;
            $period = $run->payrollPeriod;
            if ($employment === null || $employment->employee === null || ! $this->overlaps($employment, $period)) {
                throw ValidationException::withMessages(['eligibility_reason' => 'Employment dates must overlap payroll coverage to retain payable compensation.']);
            }
            if (blank($reason)) {
                throw ValidationException::withMessages(['eligibility_reason' => 'Document why compensation remains payable for this employee.']);
            }
            $oldValues = $detail->only(['eligibility_reason', 'eligibility_context']);
            $detail->update([
                'eligibility_reason' => $reason,
                'eligibility_resolved_by' => $user->getKey(),
                'eligibility_resolved_at' => now(),
                'eligibility_context' => $this->context($employment, $period),
            ]);
            $this->auditLogger->log('PAYROLL_ELIGIBILITY_RETAINED', 'Payroll', $user->getKey(), 'payroll_details', (string) $detail->getKey(), $oldValues, $detail->only(['eligibility_reason', 'eligibility_context', 'eligibility_resolved_at']));
        });
    }

    /** @return array<string, float> */
    public function totals(PayrollRun $run): array
    {
        return [
            'gross_pay' => (float) $run->details()->sum('gross_pay'),
            'total_deductions' => (float) $run->details()->sum('total_deductions'),
            'net_pay' => (float) $run->details()->sum('net_pay'),
            'employer_contributions' => (float) PayrollEmployerContribution::whereIn('payroll_detail_id', $run->details()->select('payroll_detail_id'))->sum('amount'),
        ];
    }
}
