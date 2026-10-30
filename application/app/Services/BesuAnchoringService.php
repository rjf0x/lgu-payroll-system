<?php

namespace App\Services;

use App\Models\BesuAnchor;
use App\Models\PayrollCommitment;
use App\Models\PayrollFinalization;
use App\Services\Blockchain\BesuConnectionException;
use App\Services\Blockchain\BesuJournalRecovery;
use App\Services\Blockchain\BesuRegistry;
use App\Services\Blockchain\BesuRpcClient;
use App\Services\Blockchain\BesuTransactionVerifier;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

class BesuAnchoringService
{
    public function __construct(private BesuRegistry $registry, private BesuRpcClient $rpc, private BesuTransactionVerifier $verifier, private BesuJournalRecovery $recovery) {}

    /** Runs in the authorized finalization transaction; no RPC, signing or sending. */
    public function enqueue(PayrollFinalization $finalization): void
    {
        if (! config('besu.enabled') || ! config('besu.automatic_anchoring')) {
            return;
        }
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Anchoring must be reserved atomically with finalization.');
        }
        $archive = app(PayrollSnapshotService::class)->archive($finalization);
        $commitment = PayrollCommitment::where('payroll_finalization_id', $finalization->id)->first();
        $commitment ??= PayrollCommitment::forceCreate(['payroll_finalization_id' => $finalization->id,
            'canonicalization' => CanonicalPayrollSnapshot::VERSION, 'algorithm' => 'sha256',
            'sha256' => $archive->sha256, 'submission_key' => (string) Str::uuid(), 'created_by' => $finalization->created_by,
        ]);
        $anchor = BesuAnchor::where('payroll_commitment_id', $commitment->id)->first();
        $anchor ??= BesuAnchor::forceCreate(['payroll_commitment_id' => $commitment->id,
            'reference' => '0x'.hash('sha256', 'lgu-payroll-batch-version:'.$commitment->submission_key), 'status' => 'queued',
        ]);
        if ($anchor->wasRecentlyCreated) {
            $this->event($anchor->id, 'queued');
        }
    }

    private function event(int $id, string $event): void
    {
        DB::table('besu_anchor_events')->insert(['outbox_id' => $id, 'event' => $event, 'created_at' => now()]);
    }

    private function identity(): array
    {
        return array_map(fn ($value) => is_string($value) ? strtolower($value) : $value,
            array_intersect_key(config('besu'), array_flip(['chain_id', 'genesis_hash', 'contract_address', 'runtime_code_hash', 'administrator', 'writer'])));
    }

    private function remember(BesuAnchor $anchor): void
    {
        if (! $anchor->transaction_hash) {
            return;
        }
        $existing = DB::table('besu_anchor_transactions')->where('transaction_hash', $anchor->transaction_hash)->first();
        if ($existing) {
            if ((int) $existing->outbox_id !== $anchor->id) {
                throw new BesuConnectionException('journal_conflict');
            }

            return;
        }
        $fields = array_intersect_key($anchor->getAttributes(), array_flip(['transaction_hash', 'identity', 'nonce', 'encrypted_transaction', 'status', 'block_number', 'block_hash', 'confirmed_at', 'created_at', 'updated_at']));
        DB::table('besu_anchor_transactions')->insert(['outbox_id' => $anchor->id, ...$fields]);
    }

    private function assertChainClock(): void
    {
        $block = $this->rpc->request('eth_getBlockByNumber', ['latest', false]);
        $timestamp = is_array($block) ? ($block['timestamp'] ?? null) : null;
        if (! is_string($timestamp) || ! preg_match('/\A0x[0-9a-f]{1,12}\z/i', $timestamp)) {
            throw new BesuConnectionException('invalid_rpc_response');
        }
        $age = time() - (int) hexdec(substr($timestamp, 2));
        if ($age < -30 || $age > 120) {
            throw new BesuConnectionException('chain_clock_mismatch');
        }
    }

    /** Only a canonical mined failure permits a new nonce; ambiguous sends keep their original bytes. */
    public function retryFailed(int $id): void
    {
        if (! config('besu.enabled') || ! config('besu.automatic_anchoring') || DB::transactionLevel() !== 0) {
            throw new BesuConnectionException('retry_not_available');
        }
        DB::transaction(function () use ($id) {
            $anchor = BesuAnchor::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($anchor->status !== 'failed' || ! $anchor->transaction_hash) {
                throw new BesuConnectionException('retry_requires_mined_failure');
            }
            $commitment = $this->assertSource($anchor);
            $identity = $anchor->identity;
            $expected = $this->identity();
            if (! is_array($identity)) {
                throw new BesuConnectionException('identity_changed');
            }
            ksort($identity);
            ksort($expected);
            if ($identity !== $expected) {
                throw new BesuConnectionException('identity_changed');
            }
            $proof = $this->verifier->confirm(Crypt::decryptString($anchor->encrypted_transaction), $anchor->transaction_hash, $anchor->reference, $commitment->sha256);
            if ($proof['status'] !== 'failed') {
                throw new BesuConnectionException('retry_requires_mined_failure');
            }
            $this->remember($anchor);
            DB::table('besu_anchor_transactions')->where('transaction_hash', $anchor->transaction_hash)->update([...$proof, 'updated_at' => now()]);
            DB::table('besu_anchor_outbox')->where('id', $id)->update([
                'status' => 'queued', 'transaction_hash' => null, 'encrypted_transaction' => null, 'identity' => null,
                'nonce' => null, 'block_number' => null, 'block_hash' => null, 'confirmed_at' => null,
                'last_error' => null, 'retry_count' => 0, 'next_attempt_at' => null, 'updated_at' => now(),
            ]);
            $this->event($id, 'retry_requested');
        });
    }

    private function assertSource(BesuAnchor $anchor): PayrollCommitment
    {
        $commitment = PayrollCommitment::findOrFail($anchor->payroll_commitment_id);
        $final = PayrollFinalization::findOrFail($commitment->payroll_finalization_id);
        $archive = app(PayrollSnapshotService::class)->archive($final, false);
        if (! $archive || $commitment->canonicalization !== CanonicalPayrollSnapshot::VERSION
            || $commitment->algorithm !== 'sha256' || ! hash_equals($archive->sha256, $commitment->sha256)
            || $anchor->reference !== '0x'.hash('sha256', 'lgu-payroll-batch-version:'.$commitment->submission_key)) {
            throw new BesuConnectionException('invalid_source');
        }

        return $commitment;
    }

    private function prepare(int $id): BesuAnchor
    {
        return DB::transaction(function () use ($id) {
            $anchor = BesuAnchor::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($anchor->transaction_hash || in_array($anchor->status, ['confirmed', 'failed', 'blocked'], true)) {
                return $anchor;
            }
            $commitment = $this->assertSource($anchor);
            $restored = $this->recovery->find($anchor->reference, $commitment->sha256);
            if ($restored) {
                DB::table('besu_anchor_outbox')->where('id', $id)->update([
                    'status' => 'confirmed', 'transaction_hash' => $restored['hash'], 'nonce' => $restored['nonce'],
                    'encrypted_transaction' => Crypt::encryptString($restored['raw']), 'identity' => json_encode($this->identity(), JSON_THROW_ON_ERROR),
                    'block_number' => $restored['block_number'], 'block_hash' => $restored['block_hash'], 'confirmed_at' => $restored['confirmed_at'],
                    'last_error' => null, 'next_attempt_at' => null, 'retry_count' => 0, 'updated_at' => now(),
                ]);
                $this->remember($anchor->fresh());
                DB::table('besu_anchor_transactions')->where('transaction_hash', $restored['hash'])->whereIn('status', ['signed', 'pending'])
                    ->update(['status' => 'confirmed', 'block_number' => $restored['block_number'], 'block_hash' => $restored['block_hash'], 'confirmed_at' => $restored['confirmed_at'], 'updated_at' => now()]);
                $this->event($id, 'recovered_from_chain');

                return $anchor->fresh();
            }
            $health = $this->registry->health();
            $this->assertChainClock();
            // Serialize nonce reservation across all contracts sharing this chain and writer.
            $scope = hash('sha256', config('besu.chain_id').':'.strtolower(config('besu.genesis_hash')).':'.strtolower($health['writer']));
            DB::table('besu_signer_nonces')->insertOrIgnore(['scope' => $scope, 'next_nonce' => 0]);
            $cursor = DB::table('besu_signer_nonces')->where('scope', $scope)->lockForUpdate()->first();
            $pending = $this->rpc->request('eth_getTransactionCount', [$health['writer'], 'pending']);
            if (! is_string($pending) || ! preg_match('/\A0x(?:0|[1-9a-f][a-f0-9]{0,12})\z/', $pending)) {
                throw new BesuConnectionException('invalid_nonce');
            }
            $nonce = max((int) $cursor->next_nonce, (int) hexdec(substr($pending, 2)));
            $signed = $this->registry->sign($anchor->reference, $commitment->sha256, $nonce);
            DB::table('besu_signer_nonces')->where('scope', $scope)->update(['next_nonce' => $nonce + 1]);
            DB::table('besu_anchor_outbox')->where('id', $id)->update([
                'status' => 'signed', 'identity' => json_encode($this->identity(), JSON_THROW_ON_ERROR), 'nonce' => $nonce,
                'transaction_hash' => $signed['hash'], 'encrypted_transaction' => Crypt::encryptString($signed['raw']), 'updated_at' => now(),
            ]);
            $this->event($id, 'signed');
            $this->remember($anchor->fresh());

            return $anchor->fresh();
        });
    }

    /** Journal commit precedes broadcast; an interrupted send reuses the exact same bytes/hash. */
    public function process(int $id): string
    {
        if (! config('besu.enabled') || ! config('besu.automatic_anchoring')) {
            throw new BesuConnectionException('automatic_anchoring_disabled');
        }
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Anchoring cannot send inside an outer database transaction.');
        }
        $attemptHash = null;
        try {
            $anchor = $this->prepare($id);
            $attemptHash = $anchor->transaction_hash;
            if (in_array($anchor->status, ['confirmed', 'failed', 'blocked'], true)) {
                return $anchor->status;
            }
            $commitment = $this->assertSource($anchor);
            DB::transaction(fn () => $this->remember($anchor));
            $savedIdentity = $anchor->identity;
            $expectedIdentity = $this->identity();
            // MySQL's JSON storage may reorder object keys; compare typed values, not key order.
            ksort($savedIdentity);
            ksort($expectedIdentity);
            if ($savedIdentity !== $expectedIdentity) {
                throw new BesuConnectionException('identity_changed');
            }
            $raw = Crypt::decryptString($anchor->encrypted_transaction);
            $result = $this->verifier->confirm($raw, $anchor->transaction_hash, $anchor->reference, $commitment->sha256);
            $current = BesuAnchor::findOrFail($id);
            if ($current->transaction_hash !== $attemptHash || in_array($current->status, ['confirmed', 'failed', 'blocked'], true)) {
                return $current->status;
            }
            if ($result['status'] === 'pending') {
                // Existing pending transactions need no repeat send; missing ones may be safely rebroadcast.
                if ($this->rpc->request('eth_getTransactionByHash', [$anchor->transaction_hash]) === null) {
                    // Read-only reconciliation may survive revocation; broadcasting still requires write permission.
                    $this->registry->health();
                    $this->assertChainClock();
                    DB::transaction(fn () => $this->event($id, 'broadcast_requested'));
                    $hash = $this->rpc->broadcast($raw);
                    if (strtolower($hash) !== strtolower($anchor->transaction_hash)) {
                        throw new BesuConnectionException('transaction_hash_mismatch');
                    }
                }
                DB::transaction(function () use ($id, $attemptHash) {
                    $changed = DB::table('besu_anchor_outbox')->where('id', $id)->where('transaction_hash', $attemptHash)->whereIn('status', ['signed', 'pending'])
                        ->update(['status' => 'pending', 'last_error' => null, 'retry_count' => 0, 'next_attempt_at' => now()->addSeconds(5), 'updated_at' => now()]);
                    if ($changed) {
                        DB::table('besu_anchor_transactions')->where('transaction_hash', $attemptHash)->whereIn('status', ['signed', 'pending'])->update(['status' => 'pending', 'updated_at' => now()]);
                    }
                });

                return BesuAnchor::findOrFail($id)->status;
            }
            DB::transaction(function () use ($id, $result, $attemptHash) {
                $changed = DB::table('besu_anchor_outbox')->where('id', $id)->where('transaction_hash', $attemptHash)->whereIn('status', ['signed', 'pending'])->update([...$result, 'updated_at' => now()]);
                if ($changed) {
                    DB::table('besu_anchor_outbox')->where('id', $id)->update(['last_error' => null, 'retry_count' => 0, 'next_attempt_at' => null]);
                    DB::table('besu_anchor_transactions')->where('transaction_hash', $attemptHash)->whereIn('status', ['signed', 'pending'])->update([...$result, 'updated_at' => now()]);
                    $this->event($id, $result['status']);
                }
            });

            return BesuAnchor::findOrFail($id)->status;
        } catch (Throwable $error) {
            // No raw provider/process messages, signed bytes or payroll values in audit output.
            // Keep the durable journal for reconciliation. Never label uncertainty as confirmation.
            $safe = ['rpc_unavailable', 'wrong_chain', 'wrong_genesis', 'invalid_contract', 'writer_unauthorized', 'identity_changed',
                'invalid_source', 'chain_clock_mismatch', 'commitment_conflict', 'recovery_range_exceeded', 'invalid_recovery_evidence', 'recovery_evidence_missing'];
            $reason = $error instanceof BesuConnectionException && in_array($error->reason, $safe, true) ? $error->reason : 'check_incomplete';

            return DB::transaction(function () use ($id, $reason, $attemptHash) {
                $current = BesuAnchor::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($current->transaction_hash !== $attemptHash || in_array($current->status, ['confirmed', 'failed', 'blocked'], true)) {
                    return $current->status;
                }
                $retries = min($current->retry_count + 1, 1000000);
                DB::table('besu_anchor_outbox')->where('id', $id)->update(['last_error' => $reason, 'retry_count' => $retries,
                    'next_attempt_at' => now()->addSeconds(min(300, 5 * (2 ** min($retries - 1, 6)))), 'updated_at' => now()]);
                $this->event($id, 'check_incomplete');

                return 'incomplete';
            });
        }
    }
}
