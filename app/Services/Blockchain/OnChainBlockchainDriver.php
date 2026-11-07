<?php

namespace App\Services\Blockchain;

interface OnChainBlockchainDriver extends BlockchainDriver
{
    /**
     * Return null while mining; otherwise return evidence read from the network.
     *
     * @return array{payload_hash: string, block_number: int, successful: bool}|null
     */
    public function receipt(string $transactionId, string $contractAddress): ?array;
}
