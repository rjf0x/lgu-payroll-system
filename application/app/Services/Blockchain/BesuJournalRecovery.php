<?php

namespace App\Services\Blockchain;

class BesuJournalRecovery
{
    public function __construct(private BesuRegistry $registry, private BesuRpcClient $rpc, private BesuBridge $bridge, private BesuTransactionVerifier $verifier) {}

    /** Recover public chain evidence after restoring a pre-send journal; never sign or broadcast. */
    public function find(string $reference, string $digest): ?array
    {
        $row = $this->registry->lookup($reference);
        if (! $row['exists']) {
            return null;
        }
        if (! hash_equals($digest, $row['sha256'])) {
            throw new BesuConnectionException('commitment_conflict');
        }
        $health = $this->registry->health(false);
        // Bound each log query and the total work. Older/larger chains require explicit investigation.
        if ($health['block_number'] > 100000) {
            throw new BesuConnectionException('recovery_range_exceeded');
        }
        $topics = $this->bridge->invoke('recordedFilter', ['reference' => $reference])['topics'];
        for ($start = 0; $start <= $health['block_number']; $start += 2000) {
            $logs = $this->rpc->request('eth_getLogs', [[
                'address' => $health['contract'], 'topics' => $topics,
                'fromBlock' => '0x'.dechex($start), 'toBlock' => '0x'.dechex(min($start + 1999, $health['block_number'])),
            ]]);
            if (! is_array($logs) || count($logs) > 1) {
                throw new BesuConnectionException('invalid_recovery_evidence');
            }
            if ($logs === []) {
                continue;
            }
            $hash = $logs[0]['transactionHash'] ?? null;
            if (! is_string($hash) || ! preg_match('/\A0x[a-f0-9]{64}\z/i', $hash) || ($logs[0]['removed'] ?? false)) {
                throw new BesuConnectionException('invalid_recovery_evidence');
            }
            $tx = $this->rpc->request('eth_getTransactionByHash', [$hash]);
            $restored = $this->bridge->invoke('restoreTransaction', ['transaction' => $tx]);
            $proof = $this->verifier->confirm($restored['raw'], $hash, $reference, $digest);
            if ($proof['status'] !== 'confirmed' || strtolower($restored['hash']) !== strtolower($hash)) {
                throw new BesuConnectionException('invalid_recovery_evidence');
            }

            return [...$restored, ...$proof];
        }
        throw new BesuConnectionException('recovery_evidence_missing');
    }
}
