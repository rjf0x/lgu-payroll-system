<?php

namespace App\Services\Blockchain;

interface BlockchainReader
{
    /**
     * Independently retrieve this transaction's hash commitment, never payroll data.
     * A real adapter must validate chain identity, successful inclusion, exact
     * commitment payload and the authorized confirmation/reorganization policy.
     * A transaction reference or a locally supplied hash is not confirmation.
     */
    public function receipt(string $network, string $transactionReference): BlockchainReceipt;
}
