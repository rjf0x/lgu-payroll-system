<?php

namespace App\Services\Blockchain;

interface BlockchainDriver
{
    /**
     * Anchor a payload hash and return the network transaction evidence.
     *
     * @return array{network_name: string, transaction_id: string, block_number: ?int, contract_address: ?string}
     */
    public function anchor(string $payloadHash): array;

    /**
     * A short identifier for the driver implementation.
     */
    public function name(): string;
}
