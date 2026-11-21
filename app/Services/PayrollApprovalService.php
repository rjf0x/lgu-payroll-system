<?php

namespace App\Services;

use App\Models\PayrollApproval;
use App\Models\PayrollRun;
use App\Models\User;

/**
 * Drives the payroll approval workflow and enforces separation of duties.
 *
 * The user who created or submitted a payroll run can never approve, reject,
 * or return that same run.
 */
class PayrollApprovalService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PayrollRegistrationService $registrations,
        private readonly PayrollEligibilityService $eligibility,
        private readonly PayrollComputationService $computation,
        private readonly PayrollDuplicateGuard $duplicates,
        private readonly PayslipPublicationService $payslips,
    ) {}

    public function submit(PayrollRun $run, User $user): void
    {
        $this->duplicates->transaction($run, 'submission', function () use ($run, $user): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            if (! $run->isEditable()) {
                abort(422, 'Only draft, returned, or rejected runs can be submitted.');
            }

            if (! $run->details()->exists()) {
                abort(422, 'Compute the payroll before submitting it for approval.');
            }
            $this->duplicates->validate($run, 'submission');
            $this->computation->assertAccountingPolicy($run);
            foreach ($run->details()->with(['employmentRecord.employee'])->get() as $detail) {
                $issue = $this->eligibility->issue($detail, $run->payrollPeriod);
                abort_if($issue !== null, 422, $issue ?? 'Resolve payroll eligibility before submission.');
            }
            if ($run->details()->where(fn ($query) => $query->whereNull('payable_days')->orWhereNull('input_confirmed_at')->orWhere('payroll_status', '!=', 'COMPUTED'))->exists()) {
                abort(422, 'Enter and confirm approved payroll inputs for every employee before submission.');
            }
            $this->computation->validateForSubmission($run);
            $run->update([
                'status' => PayrollRun::STATUS_PENDING_APPROVAL,
                'submitted_by' => $user->user_id,
                'submitted_at' => now(),
            ]);

            $this->record($run, PayrollApproval::ACTION_SUBMITTED, $user, 'Submitted for approval');
            $this->auditLogger->log('PAYROLL_SUBMITTED', 'Payroll', $user->user_id, 'payroll_runs', (string) $run->payroll_run_id, newValues: [
                'run_type' => $run->run_type, 'accounting_policy' => config('payroll.non_regular.'.$run->run_type),
            ]);
        });
    }

    public function approve(PayrollRun $run, User $user, ?string $remarks = null): void
    {
        $this->duplicates->transaction($run, 'approval', function () use ($run, $user, $remarks): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            $this->assertIndependentReviewer($run, $user);
            $this->duplicates->validate($run, 'approval');
            $this->computation->assertAccountingPolicy($run);
            if ($run->run_type !== 'REGULAR') {
                $this->computation->validateForSubmission($run);
            }
            $run->update(['status' => PayrollRun::STATUS_APPROVED]);

            $this->record($run, PayrollApproval::ACTION_APPROVED, $user, $remarks);
            $this->auditLogger->log('PAYROLL_APPROVED', 'Payroll', $user->user_id, 'payroll_runs', (string) $run->payroll_run_id);
        });
    }

    public function reject(PayrollRun $run, User $user, ?string $remarks = null): void
    {
        $this->duplicates->transaction($run, 'rejection', function () use ($run, $user, $remarks): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            $this->assertIndependentReviewer($run, $user);
            $run->update(['status' => PayrollRun::STATUS_REJECTED]);
            $this->payslips->withdraw($run, (int) $user->getKey(), $remarks);

            $this->record($run, PayrollApproval::ACTION_REJECTED, $user, $remarks);
            $this->auditLogger->log('PAYROLL_REJECTED', 'Payroll', $user->user_id, 'payroll_runs', (string) $run->payroll_run_id);
        });
    }

    public function returnForCorrection(PayrollRun $run, User $user, ?string $remarks = null): void
    {
        $this->duplicates->transaction($run, 'return', function () use ($run, $user, $remarks): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            $this->assertIndependentReviewer($run, $user, allowApproved: true);
            $run->update(['status' => PayrollRun::STATUS_RETURNED]);
            $this->payslips->withdraw($run, (int) $user->getKey(), $remarks);

            $this->record($run, PayrollApproval::ACTION_RETURNED, $user, $remarks);
            $this->auditLogger->log('PAYROLL_RETURNED', 'Payroll', $user->user_id, 'payroll_runs', (string) $run->payroll_run_id, newValues: [
                'reason' => $remarks,
                'status' => PayrollRun::STATUS_RETURNED,
                'approval_required' => true,
            ]);
        });
    }

    public function finalize(PayrollRun $run, User $user): void
    {
        $this->duplicates->transaction($run, 'finalization', function () use ($run, $user): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            if ($run->status !== PayrollRun::STATUS_APPROVED) {
                abort(422, 'Only an approved payroll run can be finalized.');
            }
            $this->duplicates->validate($run, 'finalization');
            $this->computation->assertAccountingPolicy($run);
            if ($run->run_type !== 'REGULAR') {
                $this->computation->validateForSubmission($run);
            }
            $this->payslips->withdraw($run, (int) $user->getKey(), 'Publication before finalization requires fresh generation and publication.', publishedOnly: true);
            $run->update([
                'status' => PayrollRun::STATUS_FINALIZED,
                'finalized_by' => $user->user_id,
                'finalized_at' => now(),
            ]);

            $this->record($run, PayrollApproval::ACTION_FINALIZED, $user, 'Payroll finalized');
            if (config('blockchain.registry.enabled')) {
                $this->registrations->capture($run);
            }
            $this->auditLogger->log('PAYROLL_FINALIZED', 'Payroll', $user->user_id, 'payroll_runs', (string) $run->payroll_run_id);
        });
    }

    /**
     * A reviewer must be neither the preparer nor the submitter of the run.
     */
    public function isIndependentReviewer(PayrollRun $run, User $user): bool
    {
        return (int) $user->user_id !== (int) $run->created_by
            && (int) $user->user_id !== (int) $run->submitted_by;
    }

    private function assertIndependentReviewer(PayrollRun $run, User $user, bool $allowApproved = false): void
    {
        if ($run->status !== PayrollRun::STATUS_PENDING_APPROVAL && ! ($allowApproved && $run->status === PayrollRun::STATUS_APPROVED)) {
            abort(422, 'This payroll run is not awaiting approval.');
        }

        if (! $this->isIndependentReviewer($run, $user)) {
            abort(403, 'The user who prepared or submitted a payroll run cannot review that same run.');
        }
    }

    private function record(PayrollRun $run, string $action, User $user, ?string $remarks): void
    {
        PayrollApproval::create([
            'payroll_run_id' => $run->payroll_run_id,
            'approval_step' => $action,
            'action' => $action,
            'acted_by' => $user->user_id,
            'remarks' => $remarks,
            'acted_at' => now(),
        ]);
    }
}
