<?php

namespace App\Services\Blockchain;

use RuntimeException;

/**
 * Placeholder for a real testnet integration (e.g. a public test network).
 * Configure credentials and implement the transaction submission before use.
 */
class TestnetBlockchainDriver implements BlockchainDriver
{
    public function anchor(string $payloadHash): array
    {
        throw new RuntimeException('The testnet blockchain driver is not configured.');
    }

    public function name(): string
    {
        return 'testnet';
    }
}
