<?php

namespace App\Services\Blockchain;

/**
 * Produces a deterministic-looking, verifiable proof without contacting any
 * external network. Suitable for the mock-defense build.
 */
class SimulatedBlockchainDriver implements BlockchainDriver
{
    public function anchor(string $payloadHash): array
    {
        $seed = $payloadHash.'|'.now()->timestamp.'|'.random_int(1, PHP_INT_MAX);

        return [
            'network_name' => (string) config('blockchain.network'),
            'transaction_id' => '0x'.substr(hash('sha256', $seed), 0, 64),
            'block_number' => random_int(1_000_000, 9_999_999),
            'contract_address' => config('blockchain.contract_address'),
        ];
    }

    public function name(): string
    {
        return 'simulated';
    }
}
