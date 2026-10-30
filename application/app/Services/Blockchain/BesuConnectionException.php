<?php

namespace App\Services\Blockchain;

use RuntimeException;

final class BesuConnectionException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        // Never chain transport/process exceptions that may contain credentials or signed bytes.
        parent::__construct('Besu connection check failed ('.$reason.').');
    }
}
