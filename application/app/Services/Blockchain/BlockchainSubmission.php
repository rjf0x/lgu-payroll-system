<?php

namespace App\Services\Blockchain;

use InvalidArgumentException;

final readonly class BlockchainSubmission
{
    public function __construct(public string $network, public string $transactionReference)
    {
        if (trim($network) === '' || strlen($network) > 120
            || trim($transactionReference) === '' || strlen($transactionReference) > 255
            || ! preg_match('/\A[\x20-\x7E]+\z/', $network.$transactionReference)) {
            throw new InvalidArgumentException('A network and transaction reference are required.');
        }
    }
}
