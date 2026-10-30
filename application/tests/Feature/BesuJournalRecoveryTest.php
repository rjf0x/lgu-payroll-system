<?php

namespace Tests\Feature;

use App\Services\Blockchain\BesuBridge;
use App\Services\Blockchain\BesuConnectionException;
use App\Services\Blockchain\BesuJournalRecovery;
use App\Services\Blockchain\BesuRegistry;
use App\Services\Blockchain\BesuRpcClient;
use App\Services\Blockchain\BesuTransactionVerifier;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BesuJournalRecoveryTest extends TestCase
{
    public function test_missing_on_chain_commitment_permits_normal_preparation_without_log_search(): void
    {
        $this->mock(BesuRegistry::class)->shouldReceive('lookup')->once()->andReturn(['exists' => false]);
        $this->mock(BesuRpcClient::class)->shouldNotReceive('request');
        $this->assertNull(app(BesuJournalRecovery::class)->find('0x'.str_repeat('1', 64), str_repeat('2', 64)));
    }

    public function test_matching_commitment_is_recovered_in_bounded_log_ranges_with_exact_receipt_verification(): void
    {
        $ref = '0x'.str_repeat('1', 64);
        $digest = str_repeat('2', 64);
        $hash = '0x'.str_repeat('3', 64);
        $registry = $this->mock(BesuRegistry::class);
        $registry->shouldReceive('lookup')->with($ref)->once()->andReturn(['exists' => true, 'sha256' => $digest]);
        $registry->shouldReceive('health')->with(false)->once()->andReturn(['block_number' => 4500, 'contract' => '0x'.str_repeat('4', 40)]);
        $bridge = $this->mock(BesuBridge::class);
        $bridge->shouldReceive('invoke')->with('recordedFilter', ['reference' => $ref])->once()->andReturn(['topics' => ['event', $ref]]);
        $bridge->shouldReceive('invoke')->with('restoreTransaction', ['transaction' => ['hash' => $hash]])->once()->andReturn(['hash' => $hash, 'raw' => '0x1234', 'nonce' => 7]);
        $ranges = [];
        $rpc = $this->mock(BesuRpcClient::class);
        $rpc->shouldReceive('request')->with('eth_getLogs', Mockery::type('array'))->andReturnUsing(function ($method, $params) use (&$ranges, $hash) {
            $ranges[] = [$params[0]['fromBlock'], $params[0]['toBlock']];

            return count($ranges) === 3 ? [['transactionHash' => $hash, 'removed' => false]] : [];
        });
        $rpc->shouldReceive('request')->with('eth_getTransactionByHash', [$hash])->once()->andReturn(['hash' => $hash]);
        $rpc->shouldNotReceive('broadcast');
        $this->mock(BesuTransactionVerifier::class)->shouldReceive('confirm')->with('0x1234', $hash, $ref, $digest)->once()->andReturn([
            'status' => 'confirmed', 'block_number' => 4100, 'block_hash' => '0x'.str_repeat('a', 64), 'confirmed_at' => now(),
        ]);
        $result = app(BesuJournalRecovery::class)->find($ref, $digest);
        $this->assertSame($hash, $result['hash']);
        $this->assertSame('confirmed', $result['status']);
        $this->assertSame([['0x0', '0x7cf'], ['0x7d0', '0xf9f'], ['0xfa0', '0x1194']], $ranges);
    }

    public static function invalidRecovery(): array
    {
        return [['conflict'], ['range'], ['missing'], ['ambiguous']];
    }

    #[DataProvider('invalidRecovery')]
    public function test_conflicting_missing_or_ambiguous_recovery_evidence_never_becomes_confirmed(string $case): void
    {
        $registry = $this->mock(BesuRegistry::class);
        $registry->shouldReceive('lookup')->andReturn(['exists' => true, 'sha256' => $case === 'conflict' ? str_repeat('f', 64) : str_repeat('2', 64)]);
        $registry->shouldReceive('health')->andReturn(['block_number' => $case === 'range' ? 100001 : 1, 'contract' => '0x'.str_repeat('4', 40)]);
        $this->mock(BesuBridge::class)->shouldReceive('invoke')->with('recordedFilter', Mockery::any())->andReturn(['topics' => []]);
        $rpc = $this->mock(BesuRpcClient::class);
        $rpc->shouldReceive('request')->with('eth_getLogs', Mockery::any())->andReturn($case === 'ambiguous' ? [[], []] : []);
        $rpc->shouldNotReceive('broadcast');
        $this->expectException(BesuConnectionException::class);
        app(BesuJournalRecovery::class)->find('0x'.str_repeat('1', 64), str_repeat('2', 64));
    }

    public function test_live_read_only_recovery_of_retained_development_commitment_needs_no_signing_files(): void
    {
        $reference = getenv('BESU_LIVE_RECOVERY_REF');
        if (! $reference) {
            $this->markTestSkipped('Opt in using BESU_LIVE_RECOVERY_REF with an existing fictional commitment reference.');
        }
        config(['besu.keystore_path' => 'nonexistent-read-only-probe', 'besu.password_path' => 'nonexistent-read-only-probe']);
        $rpc = app(BesuRpcClient::class);
        $before = $rpc->request('eth_getTransactionCount', [config('besu.writer'), 'pending']);
        $row = app(BesuRegistry::class)->lookup($reference);
        $this->assertTrue($row['exists']);
        $result = app(BesuJournalRecovery::class)->find($reference, $row['sha256']);
        $this->assertSame('confirmed', $result['status']);
        $this->assertMatchesRegularExpression('/\A0x[a-f0-9]{64}\z/i', $result['hash']);
        $this->assertSame($before,$rpc->request('eth_getTransactionCount',[config('besu.writer'), 'pending']));
    }
}
