<?php

namespace App\Services;

use App\Models\BlockchainAnchor;
use App\Models\BlockchainVerificationLog;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Blockchain\BlockchainDriver;
use App\Services\Blockchain\OnChainBlockchainDriver;
use App\Services\Blockchain\SimulatedBlockchainDriver;
use Illuminate\Support\Facades\DB;

/**
 * Retains legacy verification and local simulation for finalized payroll.
 * New network proofs use versioned payroll registration.
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
        return DB::transaction(function () use ($run, $user): BlockchainAnchor {
            $lockedRun = PayrollRun::query()->lockForUpdate()->findOrFail($run->getKey());

            return $this->submitAnchor($lockedRun, $user);
        });
    }

    private function submitAnchor(PayrollRun $run, User $user): BlockchainAnchor
    {
        abort_if($run->registration()->exists(), 422, 'This payroll uses versioned registry registration.');
        if ($run->status !== PayrollRun::STATUS_FINALIZED) {
            abort(422, 'Only a finalized payroll run can be anchored.');
        }

        $anchor = $run->anchor ?? new BlockchainAnchor(['payroll_run_id' => $run->payroll_run_id]);

        if ($anchor->exists) {
            return $anchor;
        }

        abort_unless(! config('blockchain.registry.enabled')
            && config('blockchain.driver') === 'simulated'
            && $this->driver instanceof SimulatedBlockchainDriver,
            422, 'Legacy network submission is disabled. New blockchain proofs require versioned payroll registration. Existing legacy anchors remain verifiable.');

        $hash = $this->hashPayrollRun($run);
        $result = $this->driver->anchor($hash);

        $anchor->fill([
            'payload_hash' => $hash,
            'hash_algorithm' => 'SHA-256',
            'transaction_id' => $result['transaction_id'],
            'block_number' => $result['block_number'],
            'network_name' => $result['network_name'],
            'contract_address' => $result['contract_address'],
            'anchor_status' => $result['block_number'] === null ? BlockchainAnchor::STATUS_PENDING : BlockchainAnchor::STATUS_ANCHORED,
            'anchored_by' => $user->user_id,
            'anchored_at' => $result['block_number'] === null ? null : now(),
        ])->save();

        $this->auditLogger->log(
            $result['block_number'] === null ? 'PAYROLL_ANCHOR_SUBMITTED' : 'PAYROLL_ANCHORED',
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
        $stored = (string) $anchor->payload_hash;

        if ($anchor->contract_address !== null || ! str_starts_with($anchor->network_name, 'simulated:')) {
            abort_unless($this->driver instanceof OnChainBlockchainDriver, 503, 'Select the Besu driver to verify an on-chain anchor.');
            abort_unless(is_string($anchor->contract_address), 503, 'The stored Besu contract address is missing.');
            abort_unless($anchor->network_name === 'besu:'.config('blockchain.besu.chain_id'), 503, 'Configure the original Besu chain before verifying this anchor.');
            $receipt = $this->driver->receipt((string) $anchor->transaction_id, $anchor->contract_address);
            abort_if($receipt === null, 409, 'The transaction is still pending or is not available on this Besu node. Check again later.');

            if (! $receipt['successful']) {
                $anchor->update(['anchor_status' => BlockchainAnchor::STATUS_FAILED]);
                abort(422, 'The Besu transaction failed. No successful integrity verification was recorded.');
            }

            $stored = $receipt['payload_hash'];

            DB::transaction(function () use ($anchor, $receipt, $user): void {
                $locked = BlockchainAnchor::query()->lockForUpdate()->findOrFail($anchor->getKey());

                if ($locked->anchor_status !== BlockchainAnchor::STATUS_ANCHORED) {
                    $locked->update([
                        'anchor_status' => BlockchainAnchor::STATUS_ANCHORED,
                        'block_number' => $receipt['block_number'],
                        'anchored_at' => now(),
                    ]);
                    $this->auditLogger->log('PAYROLL_ANCHORED', 'Blockchain', $user->user_id,
                        'blockchain_anchors', (string) $anchor->anchor_id,
                        newValues: ['transaction_id' => $locked->transaction_id]);
                }
            });
        } else {
            abort_unless($anchor->anchor_status === BlockchainAnchor::STATUS_ANCHORED, 422, 'Only confirmed anchors can be verified.');
        }

        $calculated = $this->hashPayrollRun($anchor->payrollRun);
        $result = hash_equals($stored, $calculated) && hash_equals($stored, (string) $anchor->payload_hash)
            ? BlockchainVerificationLog::RESULT_MATCH
            : BlockchainVerificationLog::RESULT_MISMATCH;

        $log = BlockchainVerificationLog::create([
            'anchor_id' => $anchor->anchor_id,
            'verified_by' => $user->user_id,
            'stored_hash' => $stored,
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
