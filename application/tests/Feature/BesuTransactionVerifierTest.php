<?php

namespace Tests\Feature;

use App\Services\Blockchain\BesuBridge;
use App\Services\Blockchain\BesuConnectionException;
use App\Services\Blockchain\BesuRegistry;
use App\Services\Blockchain\BesuRpcClient;
use App\Services\Blockchain\BesuTransactionVerifier;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BesuTransactionVerifierTest extends TestCase
{
    public static function receipts(): array
    {
        return array_map(fn ($case) => [$case], ['confirmed', 'pending', 'failed', 'wrong_hash', 'noncanonical', 'wrong_digest', 'wrong_event']);
    }

    #[DataProvider('receipts')]
    public function test_receipt_requires_canonical_inclusion_exact_payload_event_and_registry_value(string $case): void
    {
        $hash = '0x'.str_repeat('1', 64);
        $blockHash = '0x'.str_repeat('a', 64);
        $writer = '0x'.str_repeat('2', 40);
        $digest = str_repeat('3', 64);
        $rpc = $this->mock(BesuRpcClient::class);
        $rpc->shouldReceive('request')->andReturnUsing(function ($method) use ($case, $hash, $blockHash) {
            return match ($method) {
                'eth_getTransactionReceipt' => $case === 'pending' ? null : [
                    'transactionHash' => $case === 'wrong_hash' ? '0x'.str_repeat('f', 64) : $hash,
                    'blockNumber' => '0x100', 'blockHash' => $blockHash, 'status' => $case === 'failed' ? '0x0' : '0x1',
                ],
                'eth_getBlockByNumber' => ['hash' => $case === 'noncanonical' ? '0x'.str_repeat('b', 64) : $blockHash],
                'eth_getTransactionByHash' => ['hash' => $hash],
            };
        });
        $registry = $this->mock(BesuRegistry::class);
        // Receipt reconciliation is a read; it must not require current signing permission.
        $registry->shouldReceive('health')->with(false)->once()->andReturn(['block_number' => 256, 'writer' => $writer, 'contract' => '0x'.str_repeat('4', 40)]);
        $registry->shouldReceive('lookup')->andReturn(['exists' => true, 'sha256' => $case === 'wrong_digest' ? str_repeat('f', 64) : $digest,
            'writer' => $writer, 'anchoredAt' => '42']);
        $bridge = $this->mock(BesuBridge::class);
        $bridge->shouldReceive('invoke')->with('verifyMined', Mockery::type('array'))->andReturnUsing(function () use ($case) {
            if ($case === 'wrong_event') {
                throw new BesuConnectionException('bridge_unavailable');
            }

            return ['valid' => true, 'anchoredAt' => '42'];
        });
        if (! in_array($case, ['confirmed', 'pending', 'failed'], true)) {
            $this->expectException(BesuConnectionException::class);
        }
        $result = app(BesuTransactionVerifier::class)->confirm('0x1234', $hash, '0x'.str_repeat('5', 64), $digest);
        $this->assertSame($case, $result['status']);
        if ($case === 'confirmed') {
            $this->assertSame($blockHash, $result['block_hash']);
        }
    }
}
