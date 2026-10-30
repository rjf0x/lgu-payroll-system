<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Process;
use Throwable;

class BesuBridge
{
    public function invoke(string $operation, array $data): array
    {
        try {
            $result = Process::timeout(15)->input(json_encode([
                'operation' => $operation, 'abiPath' => config('besu.abi_path'), ...$data,
            ], JSON_THROW_ON_ERROR))->run([config('besu.node_binary'), config('besu.bridge_path')]);
            if (! $result->successful() || strlen($result->output()) > 32768) {
                throw new BesuConnectionException('bridge_unavailable');
            }
            $decoded = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($decoded) || array_key_exists('error', $decoded)) {
                throw new BesuConnectionException('bridge_unavailable');
            }

            return $decoded;
        } catch (Throwable) {
            throw new BesuConnectionException('bridge_unavailable');
        }
    }
}
