<?php

return [
    'enabled' => (bool) env('BESU_ENABLED', false),
    'automatic_anchoring' => (bool) env('BESU_AUTOMATIC_ANCHORING', false),
    'rpc_url' => env('BESU_RPC_URL', 'http://127.0.0.1:18545'),
    'chain_id' => (int) env('BESU_CHAIN_ID', 20261030),
    'genesis_hash' => env('BESU_GENESIS_HASH'),
    'contract_address' => env('BESU_CONTRACT_ADDRESS'),
    'runtime_code_hash' => env('BESU_RUNTIME_CODE_HASH'),
    'administrator' => env('BESU_ADMINISTRATOR'),
    'writer' => env('BESU_WRITER'),
    'timeout' => (int) env('BESU_RPC_TIMEOUT', 5),
    'node_binary' => env('BESU_NODE_BINARY', 'node'),
    'abi_path' => base_path('../blockchain/contracts/abi/PayrollIntegrityRegistry.json'),
    'bridge_path' => base_path('../blockchain/contracts/scripts/backend-bridge.mjs'),
    'keystore_path' => env('BESU_KEYSTORE_PATH'),
    'password_path' => env('BESU_PASSWORD_PATH'),
];
