<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class BesuService
{
    /** @return array{connected: bool, status: string, block_number: ?int} */
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
                $response->json('jsonrpc') !== '2.0' ||
                $response->json('id') !== 1 ||
                $response->json('error') !== null ||
                ! is_string($blockHex) ||
                ! preg_match('/^0x[0-9a-fA-F]{1,15}$/', $blockHex)
            ) {
                return [
                    'connected' => false,
                    'status' => 'Node unavailable',
                    'block_number' => null,
                ];
            }

            return [
                'connected' => true,
                'status' => 'Node connected',
                'block_number' => hexdec($blockHex),
            ];

        } catch (Throwable $e) {
            report($e);

            return [
                'connected' => false,
                'status' => 'Node unavailable',
                'block_number' => null,
            ];
        }
    }
}
