<?php

namespace App\Services;

use App\Models\PayrollAnchorAttempt;
use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollFinalization;
use App\Models\PayrollSubmission;
use App\Models\User;
use App\Services\Blockchain\BlockchainRejected;
use App\Services\Blockchain\BlockchainSubmitter;
use App\Services\Blockchain\BlockchainUnavailable;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class PayrollAnchoringService
{
    public function __construct(private PayrollSnapshotService $snapshots, private BlockchainSubmitter $submitter) {}

    public function bytes(PayrollBatch $batch, PayrollFinalization $finalization): string
    {
        return $this->snapshots->currentBytes($batch, $finalization);
    }

    public function digest(PayrollBatch $batch, PayrollFinalization $finalization): string
    {
        return hash('sha256', $this->bytes($batch, $finalization));
    }

    public function act(Request $request, PayrollBatch $batch, PayrollFinalization $finalization, string $action, array $data): PayrollCommitment
    {
        // Commit the reservation BEFORE calling any transport. Never retry a network side effect
        // inside an automatically retried database transaction.
        [$commitment, $attempt] = DB::transaction(function () use ($request, $batch, $finalization, $action, $data) {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);
            $current = PayrollBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('anchor', $current);
            $saved = PayrollFinalization::whereKey($finalization->id)->lockForUpdate()->firstOrFail();
            abort_unless($saved->payroll_batch_id === $current->id, 404);
            $submission = PayrollSubmission::findOrFail($saved->payroll_submission_id);
            abort_unless(app(PayrollWorkflowService::class)->isIndependent($actor, $current, $submission), 403);
            abort_unless(in_array($action, ['prepare', 'submit'], true), 404);
            $data = Validator::make($data, [
                'sha256' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
                'demonstration_confirmed' => ['required', 'accepted'],
            ])->validate();
            $digest = $this->digest($current, $saved);
            abort_unless(hash_equals($digest, $data['sha256']), 409, 'The finalized snapshot changed. Reload before preparing an anchoring request.');
            $commitment = PayrollCommitment::where('payroll_finalization_id', $saved->id)->first();
            if ($commitment) {
                abort_unless($commitment->canonicalization === CanonicalPayrollSnapshot::VERSION
                    && $commitment->algorithm === 'sha256' && hash_equals($commitment->sha256, $digest), 409, 'The saved local commitment differs. Submission is blocked.');
                $this->snapshots->archive($saved);
            } else {
                abort_unless($action === 'prepare', 409, 'Prepare a local commitment before submitting.');
                $archive = $this->snapshots->archive($saved);
                $commitment = PayrollCommitment::forceCreate([
                    'payroll_finalization_id' => $saved->id, 'canonicalization' => CanonicalPayrollSnapshot::VERSION,
                    'algorithm' => 'sha256', 'sha256' => $archive->sha256, 'submission_key' => (string) Str::uuid(), 'created_by' => $actor->id,
                ]);
            }
            if ($action === 'prepare') {
                return [$commitment, null];
            }
            // This must be the outermost transaction so its return means the
            // reservation is durably committed before any submission interface runs.
            abort_unless(DB::transactionLevel() === 1, 409, 'Submission cannot run inside an outer database transaction. Retry after that transaction ends.');
            $last = PayrollAnchorAttempt::where('payroll_commitment_id', $commitment->id)->orderByDesc('id')->first();
            // Unknown or interrupted sends must be reconciled by a future real adapter,
            // never blindly retried. Pending references must not be resubmitted either.
            if ($last && in_array($last->status, ['submitting', 'unknown', 'pending'], true)) {
                return [$commitment, null];
            }
            $attempt = PayrollAnchorAttempt::forceCreate([
                'payroll_commitment_id' => $commitment->id, 'status' => 'submitting', 'created_by' => $actor->id,
            ]);

            return [$commitment, $attempt];
        });
        if ($attempt) {
            $fields = ['status' => 'unknown', 'network' => null, 'transaction_reference' => null];
            try {
                $result = $this->submitter->submit($commitment->sha256, $commitment->submission_key);
                $fields = ['status' => 'pending', 'network' => $result->network, 'transaction_reference' => $result->transactionReference];
            } catch (BlockchainUnavailable) {
                $fields['status'] = 'unavailable';
            } catch (BlockchainRejected) {
                $fields['status'] = 'failed';
            } catch (Throwable) {
                // Do not persist or expose provider exceptions: they can contain credentials.
                // A lost response may follow acceptance; preserve uncertainty and block retries.
            }
            DB::table('payroll_anchor_attempts')->where('id', $attempt->id)->where('status', 'submitting')
                ->update([...$fields, 'completed_at' => now()]);
        }

        return $commitment;
    }
}
