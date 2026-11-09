<?php

namespace App\Services;

use App\Models\PayrollApproval;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Drives the payroll approval workflow and enforces separation of duties.
 *
 * The user who created or submitted a payroll run can never approve, reject,
 * or return that same run.
 */
class PayrollApprovalService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function submit(PayrollRun $run, User $user): void
    {
        DB::transaction(function () use ($run, $user): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            if (! $run->isEditable()) {
                abort(422, 'Only draft, returned, or rejected runs can be submitted.');
            }

            if (! $run->details()->exists()) {
                abort(422, 'Compute the payroll before submitting it for approval.');
            }
            if ($run->details()->where(fn ($query) => $query->whereNull('payable_days')->orWhereNull('input_confirmed_at')->orWhereNull('input_reference')->orWhere('input_reference', ''))->exists()) {
                abort(422, 'Enter and confirm approved payroll inputs for every employee before submission.');
            }
            $run->update([
                'status' => PayrollRun::STATUS_PENDING_APPROVAL,
                'submitted_by' => $user->user_id,
                'submitted_at' => now(),
            ]);

            $this->record($run, PayrollApproval::ACTION_SUBMITTED, $user, 'Submitted for approval');
            $this->auditLogger->log('PAYROLL_SUBMITTED', 'Payroll', $user->user_id, 'payroll_runs', (string) $run->payroll_run_id);
        });
    }

    public function approve(PayrollRun $run, User $user, ?string $remarks = null): void
    {
        DB::transaction(function () use ($run, $user, $remarks): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            $this->assertIndependentReviewer($run, $user);
            $run->update(['status' => PayrollRun::STATUS_APPROVED]);

            $this->record($run, PayrollApproval::ACTION_APPROVED, $user, $remarks);
            $this->auditLogger->log('PAYROLL_APPROVED', 'Payroll', $user->user_id, 'payroll_runs', (string) $run->payroll_run_id);
        });
    }

    public function reject(PayrollRun $run, User $user, ?string $remarks = null): void
    {
        DB::transaction(function () use ($run, $user, $remarks): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            $this->assertIndependentReviewer($run, $user);
            $run->update(['status' => PayrollRun::STATUS_REJECTED]);

            $this->record($run, PayrollApproval::ACTION_REJECTED, $user, $remarks);
            $this->auditLogger->log('PAYROLL_REJECTED', 'Payroll', $user->user_id, 'payroll_runs', (string) $run->payroll_run_id);
        });
    }

    public function returnForCorrection(PayrollRun $run, User $user, ?string $remarks = null): void
    {
        DB::transaction(function () use ($run, $user, $remarks): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            $this->assertIndependentReviewer($run, $user, allowApproved: true);
            $run->update(['status' => PayrollRun::STATUS_RETURNED]);

            $this->record($run, PayrollApproval::ACTION_RETURNED, $user, $remarks);
            $this->auditLogger->log('PAYROLL_RETURNED', 'Payroll', $user->user_id, 'payroll_runs', (string) $run->payroll_run_id);
        });
    }

    public function finalize(PayrollRun $run, User $user): void
    {
        DB::transaction(function () use ($run, $user): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            if ($run->status !== PayrollRun::STATUS_APPROVED) {
                abort(422, 'Only an approved payroll run can be finalized.');
            }
            $run->update([
                'status' => PayrollRun::STATUS_FINALIZED,
                'finalized_by' => $user->user_id,
                'finalized_at' => now(),
            ]);

            $this->record($run, PayrollApproval::ACTION_FINALIZED, $user, 'Payroll finalized');
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
