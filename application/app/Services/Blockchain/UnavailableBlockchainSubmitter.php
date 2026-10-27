<?php

namespace App\Services\Blockchain;

/** No transport, credentials, simulated transaction, or network side effect. */
class UnavailableBlockchainSubmitter implements BlockchainSubmitter
{
    public function submit(string $sha256, string $idempotencyKey): BlockchainSubmission
    {
        throw new BlockchainUnavailable('No authorized blockchain test network is available.');
    }
}
