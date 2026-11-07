<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blockchain Anchor Driver
    |--------------------------------------------------------------------------
    |
    | Only a deterministic hash of finalized payroll data is anchored; no
    | confidential payroll or employee detail ever leaves the application.
    | The "simulated" driver produces a verifiable local proof for the
    | mock-defense build. A real network driver can replace it later.
    |
    */

    'driver' => env('BLOCKCHAIN_DRIVER', 'simulated'),

    'network' => env('BLOCKCHAIN_NETWORK', 'LGU-Simulated-Network'),

    'contract_address' => env('BLOCKCHAIN_CONTRACT_ADDRESS'),

    'hash_algorithm' => 'sha256',

];
