<?php

namespace App\Services\Blockchain;

use InvalidArgumentException;

final readonly class BlockchainReceipt
{
    public function __construct(
        public string $network,
        public string $transactionReference,
        public string $status,
        public ?string $sha256 = null,
        public ?string $blockReference = null,
        public int $confirmations = 0,
    ) {
        // Reuse the submission reference's bounded, non-control-text contract.
        new BlockchainSubmission($network, $transactionReference);
        if (! in_array($status, ['pending', 'failed', 'confirmed'], true)
            || ($sha256 !== null && ! preg_match('/\A[a-f0-9]{64}\z/', $sha256))
            || ($blockReference !== null && (! preg_match('/\A[\x21-\x7E]{1,255}\z/', $blockReference)))
            || $confirmations < 0 || $confirmations > 2147483647
            || ($status === 'confirmed' && ($sha256 === null || $blockReference === null || $confirmations < 1))) {
            throw new InvalidArgumentException('The blockchain receipt is incomplete or unsupported.');
        }
    }
}
