<?php

namespace App\Services\Blockchain;

/**
 * Produces simulated metadata without contacting an external network.
 * Suitable for isolated tests; this is not independent blockchain evidence.
 */
class SimulatedBlockchainDriver implements BlockchainDriver
{
    public function anchor(string $payloadHash): array
    {
        $seed = $payloadHash.'|'.now()->timestamp.'|'.random_int(1, PHP_INT_MAX);

        return [
            'network_name' => 'simulated:'.config('blockchain.network'),
            'transaction_id' => '0x'.substr(hash('sha256', $seed), 0, 64),
            'block_number' => random_int(1_000_000, 9_999_999),
            'contract_address' => null,
        ];
    }

    public function name(): string
    {
        return 'simulated';
    }
}
