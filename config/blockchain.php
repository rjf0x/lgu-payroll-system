<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blockchain Anchor Driver
    |--------------------------------------------------------------------------
    |
    | Only a deterministic hash of finalized payroll data is anchored; no
    | confidential payroll or employee detail ever leaves the application.
    | The "simulated" driver produces local demonstration records only.
    | The legacy Besu driver verifies historical proofs but cannot submit.
    | New network proofs use the versioned registry configured below.
    |
    */

    'driver' => env('BLOCKCHAIN_DRIVER', 'simulated'),

    'network' => env('BLOCKCHAIN_NETWORK', 'LGU-Simulated-Network'),

    'contract_address' => env('BLOCKCHAIN_CONTRACT_ADDRESS'),

    'hash_algorithm' => 'sha256',

    'registry' => [
        'enabled' => (bool) env('PAYROLL_REGISTRY_ENABLED', false),
        'organization_id' => env('PAYROLL_REGISTRY_ORGANIZATION_ID'),
        'bridge_url' => env('PAYROLL_REGISTRY_BRIDGE_URL', 'http://127.0.0.1:3100'),
        'bridge_token' => env('PAYROLL_REGISTRY_BRIDGE_TOKEN'),
        'chain_id' => env('PAYROLL_REGISTRY_CHAIN_ID'),
        'genesis_hash' => env('PAYROLL_REGISTRY_GENESIS_HASH'),
        'contract_address' => env('PAYROLL_REGISTRY_CONTRACT_ADDRESS'),
        'code_hash' => env('PAYROLL_REGISTRY_CODE_HASH'),
        'signer_address' => env('PAYROLL_REGISTRY_SIGNER_ADDRESS'),
        'deployment_block' => env('PAYROLL_REGISTRY_DEPLOYMENT_BLOCK'),
        'pilot_enabled' => (bool) env('BESU_REGISTRY_PILOT', false),
        'restore_check' => (bool) env('BESU_REGISTRY_RESTORE_CHECK', false),
        'operations' => [
            'stale_after_seconds' => (int) env('PAYROLL_REGISTRY_STALE_AFTER_SECONDS', 600),
            'max_block_age_seconds' => (int) env('PAYROLL_REGISTRY_MAX_BLOCK_AGE_SECONDS', 60),
            'validator_owner' => env('PAYROLL_REGISTRY_VALIDATOR_OWNER'),
            'signer_owner' => env('PAYROLL_REGISTRY_SIGNER_OWNER'),
            'payroll_owner' => env('PAYROLL_REGISTRY_PAYROLL_OWNER'),
        ],
    ],

    'besu' => [
        'rpc_url' => env('BESU_RPC_URL', 'http://127.0.0.1:28545'),
        'signer_url' => env('BESU_SIGNER_URL', 'http://127.0.0.1:29000'),
        'chain_id' => (int) env('BESU_CHAIN_ID', 1337),
        'from_address' => env('BESU_FROM_ADDRESS'),
        'gas' => env('BESU_GAS', '0x30d40'),
        'gas_price' => env('BESU_GAS_PRICE', '0x0'),
        'live_tests' => (bool) env('BESU_LIVE_TESTS', false),
    ],

];
