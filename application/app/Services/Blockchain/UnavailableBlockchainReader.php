<?php

namespace App\Services\Blockchain;

class UnavailableBlockchainReader implements BlockchainReader
{
    public function receipt(string $network, string $transactionReference): BlockchainReceipt
    {
        throw new BlockchainUnavailable('No authorized blockchain test network is available.');
    }
}
