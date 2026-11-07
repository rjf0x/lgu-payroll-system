<?php

namespace App\Services;

use App\Models\BlockchainAnchor;
use App\Models\BlockchainVerificationLog;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Blockchain\BlockchainDriver;

/**
 * Anchors a deterministic hash of a finalized payroll run and verifies that the
 * anchored proof still matches the stored payroll data.
 *
 * Confidential employee and payroll detail stays in MySQL; only the hash and
 * transaction metadata are anchored.
 */
class BlockchainAnchorService
{
    public function __construct(
        private readonly BlockchainDriver $driver,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Build a deterministic SHA-256 hash of the run's payroll details.
     */
    public function hashPayrollRun(PayrollRun $run): string
    {
        $run->load(['details.earnings', 'details.deductions']);

        $lines = [];

        foreach ($run->details->sortBy('employment_id')->values() as $detail) {
            $earnings = $detail->earnings
                ->sortBy(fn ($earning) => $earning->earning_name_snapshot.'|'.number_format((float) $earning->amount, 2, '.', ''))
                ->map(fn ($earning) => $earning->earning_name_snapshot.':'.number_format((float) $earning->amount, 2, '.', ''))
                ->values()
                ->all();

            $deductions = $detail->deductions
                ->sortBy(fn ($deduction) => $deduction->deduction_name_snapshot.'|'.number_format((float) $deduction->amount, 2, '.', ''))
                ->map(fn ($deduction) => $deduction->deduction_name_snapshot.':'.number_format((float) $deduction->amount, 2, '.', ''))
                ->values()
                ->all();

            $lines[] = implode('|', array_merge([
                $detail->employee_number_snapshot,
                $detail->employee_name_snapshot,
                number_format((float) $detail->basic_pay, 2, '.', ''),
                number_format((float) $detail->gross_pay, 2, '.', ''),
                number_format((float) $detail->total_deductions, 2, '.', ''),
                number_format((float) $detail->nthp_before_lbp, 2, '.', ''),
                number_format((float) $detail->lbp_loan_deduction, 2, '.', ''),
                number_format((float) $detail->net_pay, 2, '.', ''),
            ], $earnings, $deductions));
        }

        return hash((string) config('blockchain.hash_algorithm', 'sha256'), implode("\n", $lines));
    }

    public function anchor(PayrollRun $run, User $user): BlockchainAnchor
    {
        if ($run->status !== PayrollRun::STATUS_FINALIZED) {
            abort(422, 'Only a finalized payroll run can be anchored.');
        }

        $anchor = $run->anchor ?? new BlockchainAnchor(['payroll_run_id' => $run->payroll_run_id]);

        if ($anchor->exists && $anchor->anchor_status === BlockchainAnchor::STATUS_ANCHORED) {
            return $anchor;
        }

        $hash = $this->hashPayrollRun($run);
        $result = $this->driver->anchor($hash);

        $anchor->fill([
            'payload_hash' => $hash,
            'hash_algorithm' => 'SHA-256',
            'transaction_id' => $result['transaction_id'],
            'block_number' => $result['block_number'],
            'network_name' => $result['network_name'],
            'contract_address' => $result['contract_address'],
            'anchor_status' => BlockchainAnchor::STATUS_ANCHORED,
            'anchored_by' => $user->user_id,
            'anchored_at' => now(),
        ])->save();

        $this->auditLogger->log(
            'PAYROLL_ANCHORED',
            'Blockchain',
            $user->user_id,
            'blockchain_anchors',
            (string) $anchor->anchor_id,
            newValues: ['payload_hash' => $hash, 'network' => $result['network_name']],
        );

        return $anchor;
    }

    public function verify(BlockchainAnchor $anchor, User $user): BlockchainVerificationLog
    {
        $calculated = $this->hashPayrollRun($anchor->payrollRun);
        $result = hash_equals((string) $anchor->payload_hash, $calculated)
            ? BlockchainVerificationLog::RESULT_MATCH
            : BlockchainVerificationLog::RESULT_MISMATCH;

        $log = BlockchainVerificationLog::create([
            'anchor_id' => $anchor->anchor_id,
            'verified_by' => $user->user_id,
            'stored_hash' => $anchor->payload_hash,
            'calculated_hash' => $calculated,
            'verification_result' => $result,
            'remarks' => $result === BlockchainVerificationLog::RESULT_MATCH
                ? 'Anchored hash matches the stored payroll data.'
                : 'Anchored hash does not match the stored payroll data.',
            'verified_at' => now(),
        ]);

        $this->auditLogger->log(
            'PAYROLL_VERIFIED',
            'Blockchain',
            $user->user_id,
            'blockchain_verification_logs',
            (string) $log->verification_id,
            newValues: ['result' => $result],
        );

        return $log;
    }
}
