<?php

namespace App\Services;

use App\Models\PayrollAnchorAttempt;
use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollFinalization;
use App\Models\PayrollVerification;
use App\Models\User;
use App\Services\Blockchain\BlockchainReader;
use App\Services\Blockchain\BlockchainSubmission;
use App\Services\Blockchain\BlockchainUnavailable;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class PayrollVerificationService
{
    public const MESSAGES = [
        'not_prepared' => 'No local commitment has been prepared. No blockchain verification exists.',
        'invalid_snapshot' => 'The finalized snapshot is invalid or cannot be serialized. Verification is blocked.',
        'invalid_commitment' => 'The saved commitment has an unsupported format or invalid hash. Verification is blocked.',
        'snapshot_changed' => 'The current snapshot differs from the saved local commitment. This is a local mismatch; no chain match is established.',
        'not_submitted' => 'The local hash matches, but no blockchain submission exists.',
        'network_unavailable' => 'The network reader is unavailable. A local hash match is not blockchain verification.',
        'submission_failed' => 'Submission was rejected. No successful blockchain anchor is established.',
        'acceptance_unknown' => 'Submission acceptance is uncertain or interrupted. No confirmed receipt is available.',
        'invalid_reference' => 'The saved transaction reference is incomplete. No blockchain verification is possible.',
        'receipt_unavailable' => 'The receipt could not be checked. No confirmed blockchain match is established.',
        'receipt_identity_mismatch' => 'The retrieved receipt does not identify the requested network and transaction.',
        'receipt_pending' => 'The transaction is pending. It must not be treated as verified.',
        'receipt_failed' => 'The retrieved transaction failed. No successful anchor is established.',
        'chain_hash_mismatch' => 'The confirmed network commitment differs from the current finalized snapshot.',
        'confirmed_match' => 'The finalized snapshot and saved digest match the independently retrieved confirmed commitment at the recorded check time.',
    ];

    public function __construct(private CanonicalPayrollSnapshot $canonical, private BlockchainReader $reader) {}

    /** Local inspection only. It must also hash changed, arithmetically invalid snapshots. */
    public function inspect(PayrollBatch $batch, PayrollFinalization $finalization): array
    {
        abort_unless($finalization->payroll_batch_id === $batch->id && $batch->status === 'finalized', 404);
        abort_unless($batch->is_demonstration, 409, 'Real payroll verification remains blocked pending client validation.');
        $commitment = PayrollCommitment::where('payroll_finalization_id', $finalization->id)->first();
        $attempt = $commitment ? PayrollAnchorAttempt::where('payroll_commitment_id', $commitment->id)->orderByDesc('id')->first() : null;
        $result = [
            'payroll_finalization_id' => $finalization->id, 'payroll_commitment_id' => $commitment?->id,
            'payroll_anchor_attempt_id' => $attempt?->id, 'status' => 'invalid_snapshot', 'local_status' => 'invalid',
            'reason_code' => 'invalid_snapshot', 'current_hash' => null, 'saved_hash' => $commitment?->sha256,
            'network_hash' => null, 'network' => null, 'transaction_reference' => null,
            'block_reference' => null, 'confirmations' => 0,
        ];
        if (! is_array($finalization->snapshot)) {
            return $result;
        }
        try {
            $result['current_hash'] = hash('sha256', $this->canonical->encodeFinalization($batch->id, $finalization->id, $finalization->version, $finalization->snapshot));
        } catch (InvalidArgumentException|JsonException) {
            return $result;
        }
        $valid = true;
        try {
            app(PayrollAnchoringService::class)->bytes($batch, $finalization);
        } catch (HttpException $error) {
            if ($error->getStatusCode() !== 409) {
                throw $error;
            }
            $valid = false;
        }
        if (! $commitment) {
            return $valid ? array_replace($result, ['status' => 'not_anchored', 'local_status' => 'not_prepared', 'reason_code' => 'not_prepared']) : $result;
        }
        if ($commitment->canonicalization !== CanonicalPayrollSnapshot::VERSION || $commitment->algorithm !== 'sha256'
            || ! preg_match('/\A[a-f0-9]{64}\z/', $commitment->sha256)) {
            return array_replace($result, ['status' => 'failed', 'reason_code' => 'invalid_commitment']);
        }
        if (! hash_equals($commitment->sha256, $result['current_hash'])) {
            return array_replace($result, ['status' => 'mismatched', 'local_status' => 'mismatch', 'reason_code' => 'snapshot_changed']);
        }
        if (! $valid) {
            return $result;
        }
        $state = match ($attempt?->status) {
            null => ['not_anchored', 'not_submitted'],
            'unavailable' => ['unavailable', 'network_unavailable'],
            'failed' => ['failed', 'submission_failed'],
            'pending' => ['pending', 'receipt_pending'],
            default => ['pending', 'acceptance_unknown'],
        };

        return array_replace($result, ['status' => $state[0], 'local_status' => 'match', 'reason_code' => $state[1]]);
    }

    public function check(Request $request, PayrollBatch $batch, PayrollFinalization $finalization, array $data): PayrollVerification
    {
        // Reader calls are read-only; the transaction locks the checked local version
        // and atomically retains its evidence. No blockchain submission occurs here.
        try {
            return DB::transaction(function () use ($request, $batch, $finalization, $data) {
                $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);
                $current = PayrollBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('verifyIntegrity', $current);
                $saved = PayrollFinalization::whereKey($finalization->id)->lockForUpdate()->firstOrFail();
                abort_unless($saved->payroll_batch_id === $current->id, 404);
                abort_unless($current->is_demonstration, 409, 'Only demonstration payroll verification is available.');
                $data = Validator::make($data, ['request_key' => ['required', 'uuid']])->validate();
                $key = strtolower($data['request_key']);
                $previous = PayrollVerification::where('request_key', $key)->first();
                if ($previous) {
                    abort_unless($previous->payroll_finalization_id === $saved->id && $previous->created_by === $actor->id, 409, 'This verification request belongs to a different check.');

                    return $previous;
                }
                $result = $this->inspect($current, $saved);
                if ($result['status'] === 'pending' && $result['reason_code'] === 'receipt_pending') {
                    $attempt = PayrollAnchorAttempt::findOrFail($result['payroll_anchor_attempt_id']);
                    try {
                        // Reject corrupt references before allowing any configured network lookup.
                        new BlockchainSubmission($attempt->network ?? '', $attempt->transaction_reference ?? '');
                    } catch (InvalidArgumentException) {
                        $result['status'] = 'failed';
                        $result['reason_code'] = 'invalid_reference';

                        return PayrollVerification::forceCreate([...$result, 'request_key' => $key, 'created_by' => $actor->id, 'created_at' => now()]);
                    }
                    $result['network'] = $attempt->network;
                    $result['transaction_reference'] = $attempt->transaction_reference;
                    try {
                        $receipt = $this->reader->receipt($attempt->network, $attempt->transaction_reference);
                        if ($receipt->network !== $attempt->network || $receipt->transactionReference !== $attempt->transaction_reference) {
                            $result['status'] = 'failed';
                            $result['reason_code'] = 'receipt_identity_mismatch';
                        } else {
                            $result['network_hash'] = $receipt->sha256;
                            $result['block_reference'] = $receipt->blockReference;
                            $result['confirmations'] = $receipt->confirmations;
                            $result['status'] = match ($receipt->status) {
                                'confirmed' => hash_equals($result['current_hash'], $receipt->sha256) ? 'verified' : 'mismatched',
                                'failed' => 'failed', default => 'pending',
                            };
                            $result['reason_code'] = match ($result['status']) {
                                'verified' => 'confirmed_match', 'mismatched' => 'chain_hash_mismatch',
                                'failed' => 'receipt_failed', default => 'receipt_pending',
                            };
                        }
                    } catch (BlockchainUnavailable) {
                        $result['status'] = 'unavailable';
                        $result['reason_code'] = 'network_unavailable';
                    } catch (Throwable) {
                        // Provider exceptions can contain credentials. Retain only safe reason codes.
                        $result['status'] = 'unavailable';
                        $result['reason_code'] = 'receipt_unavailable';
                    }
                }

                return PayrollVerification::forceCreate([...$result, 'request_key' => $key, 'created_by' => $actor->id, 'created_at' => now()]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This verification request was used concurrently. Reload before checking again.');
        }
    }
}
