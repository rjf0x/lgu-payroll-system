<?php

namespace App\Services\Blockchain;

interface BlockchainSubmitter
{
    /**
     * Send only the SHA-256 digest. Reuse the key across safe retries.
     * Return a real transaction reference, which remains pending, never verified.
     * Unavailable/Rejected guarantee no acceptance; all other exceptions are ambiguous.
     */
    public function submit(string $sha256, string $idempotencyKey): BlockchainSubmission;
}
