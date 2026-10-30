<?php

namespace App\Services\Blockchain;

use App\Models\BesuAnchor;
use App\Models\PayrollCommitment;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class BesuIntegrityReader
{
    public function __construct(private BesuTransactionVerifier $verifier, private BesuRegistry $registry) {}

    /** Read-only: no signing, broadcasting, nonce allocation or journal updates. */
    public function check(BesuAnchor $anchor, PayrollCommitment $commitment): array
    {
        if (! config('besu.enabled')) {
            return ['status' => 'unavailable', 'reason_code' => 'network_unavailable'];
        }
        $expected = array_intersect_key(config('besu'), array_flip(['chain_id', 'genesis_hash', 'contract_address', 'runtime_code_hash', 'administrator', 'writer']));
        $expected = array_map(fn ($value) => is_string($value) ? strtolower($value) : $value, $expected);
        $identity = $anchor->identity;
        if (! is_array($identity)) {
            return ['status' => 'failed', 'reason_code' => 'invalid_reference'];
        }
        ksort($expected);
        ksort($identity);
        if ($identity !== $expected || $anchor->payroll_commitment_id !== $commitment->id
            || $anchor->reference !== '0x'.hash('sha256', 'lgu-payroll-batch-version:'.$commitment->submission_key)
            || ! is_string($anchor->transaction_hash) || ! preg_match('/\A0x[a-f0-9]{64}\z/i', $anchor->transaction_hash)
            || ! is_string($anchor->encrypted_transaction) || strlen($anchor->encrypted_transaction) > 32768) {
            return ['status' => 'failed', 'reason_code' => 'invalid_reference'];
        }
        try {
            $proof = $this->verifier->confirm(Crypt::decryptString($anchor->encrypted_transaction), $anchor->transaction_hash, $anchor->reference, $commitment->sha256);
            if ($proof['status'] !== 'confirmed') {
                return ['status' => $proof['status'] === 'failed' ? 'failed' : 'pending',
                    'reason_code' => $proof['status'] === 'failed' ? 'receipt_failed' : 'receipt_pending'];
            }
            // Display a freshly retrieved contract value, never a cached local confirmation label.
            $row = $this->registry->lookup($anchor->reference);
            $matches = $row['exists'] && hash_equals($commitment->sha256, $row['sha256'])
                && strtolower($row['writer']) === $identity['writer'];

            return ['status' => $matches ? 'verified' : 'mismatched', 'reason_code' => $matches ? 'confirmed_match' : 'chain_hash_mismatch',
                'network_hash' => $row['exists'] ? $row['sha256'] : null, 'block_reference' => $proof['block_hash'], 'confirmations' => 1];
        } catch (BesuConnectionException $error) {
            return $error->reason === 'commitment_mismatch'
                ? ['status' => 'mismatched', 'reason_code' => 'chain_hash_mismatch']
                : ['status' => 'unavailable', 'reason_code' => 'receipt_unavailable'];
        } catch (Throwable) {
            // Keystore material is never loaded; decrypted signed bytes/provider failures must not leak.
            return ['status' => 'unavailable', 'reason_code' => 'receipt_unavailable'];
        }
    }
}
