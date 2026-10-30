<?php

namespace App\Services\Blockchain;

class BesuTransactionVerifier
{
    public function __construct(private BesuRpcClient $rpc, private BesuRegistry $registry, private BesuBridge $bridge) {}

    /** A receipt alone is insufficient: independently check canonical inclusion and registry state. */
    public function confirm(string $raw, string $hash, string $reference, string $digest): array
    {
        // A writer may be revoked after mining. Historical confirmation must remain readable.
        $health = $this->registry->health(false);
        $receipt = $this->rpc->request('eth_getTransactionReceipt', [$hash]);
        if ($receipt === null) {
            return ['status' => 'pending'];
        }
        if (! is_array($receipt) || ! is_string($receipt['blockNumber'] ?? null)
            || ! preg_match('/\A0x(?:0|[1-9a-f][a-f0-9]{0,12})\z/', $receipt['blockNumber'])
            || ! is_string($receipt['blockHash'] ?? null) || ! preg_match('/\A0x[a-f0-9]{64}\z/i', $receipt['blockHash'])
            || ! is_string($receipt['transactionHash'] ?? null) || strtolower($receipt['transactionHash']) !== strtolower($hash)) {
            throw new BesuConnectionException('invalid_receipt');
        }
        $height = (int) hexdec(substr($receipt['blockNumber'], 2));
        $block = $this->rpc->request('eth_getBlockByNumber', [$receipt['blockNumber'], false]);
        if (! is_array($block) || ($block['hash'] ?? null) !== $receipt['blockHash'] || $height > $health['block_number']) {
            throw new BesuConnectionException('noncanonical_receipt');
        }
        if (($receipt['status'] ?? null) === '0x0') {
            return ['status' => 'failed', 'block_number' => $height, 'block_hash' => $receipt['blockHash']];
        }
        $transaction = $this->rpc->request('eth_getTransactionByHash', [$hash]);
        $verified = $this->bridge->invoke('verifyMined', [
            'raw' => $raw, 'transaction' => $transaction, 'receipt' => $receipt,
            'contract' => $health['contract'], 'writer' => $health['writer'], 'reference' => $reference, 'sha256' => $digest,
        ]);
        $row = $this->registry->lookup($reference);
        $again = $this->rpc->request('eth_getBlockByNumber', [$receipt['blockNumber'], false]);
        if (($verified['valid'] ?? null) !== true || ! $row['exists'] || ! hash_equals($digest, $row['sha256'])
            || strtolower($row['writer']) !== strtolower($health['writer']) || $row['anchoredAt'] !== ($verified['anchoredAt'] ?? null)
            || ! is_array($again) || ($again['hash'] ?? null) !== $receipt['blockHash']) {
            throw new BesuConnectionException('commitment_mismatch');
        }

        return ['status' => 'confirmed', 'block_number' => $height, 'block_hash' => $receipt['blockHash'], 'confirmed_at' => now()];
    }
}
