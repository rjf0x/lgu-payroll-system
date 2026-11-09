<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class BesuService
{
    public function checkConnection(): array
    {
        try {
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->post(config('services.besu.rpc_url'), [
                    'jsonrpc' => '2.0',
                    'method' => 'eth_blockNumber',
                    'params' => [],
                    'id' => 1,
                ]);

            $blockHex = $response->json('result');

            if (
                ! $response->successful() ||
                ! is_string($blockHex) ||
                ! preg_match('/^0x[0-9a-fA-F]+$/', $blockHex)
            ) {
                return [
                    'connected' => false,
                    'status' => 'Disconnected',
                    'block_number' => null,
                ];
            }

            return [
                'connected' => true,
                'status' => 'Connected',
                'block_number' => hexdec($blockHex),
            ];

        } catch (Throwable $e) {
            report($e);

            return [
                'connected' => false,
                'status' => 'Disconnected',
                'block_number' => null,
            ];
        }
    }
}
