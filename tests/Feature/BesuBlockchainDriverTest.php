<?php

namespace Tests\Feature;

use App\Services\Blockchain\BesuBlockchainDriver;
use App\Services\Blockchain\BlockchainDriver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BesuBlockchainDriverTest extends TestCase
{
    private const CONTRACT = '0x1111111111111111111111111111111111111111';

    private const SENDER = '0x2222222222222222222222222222222222222222';

    private const TRANSACTION = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const PAYLOAD = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'blockchain.driver' => 'besu',
            'blockchain.contract_address' => self::CONTRACT,
            'blockchain.besu.from_address' => self::SENDER,
            'blockchain.besu.chain_id' => 1337,
            'blockchain.besu.rpc_url' => 'http://besu.test',
            'blockchain.besu.signer_url' => 'http://signer.test',
            'blockchain.besu.gas' => '0x30d40',
            'blockchain.besu.gas_price' => '0x0',
        ]);
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeNetwork(array $overrides = []): void
    {
        Http::fake([
            'http://besu.test' => function (Request $request) use ($overrides) {
                $results = [
                    'eth_chainId' => '0x539',
                    'eth_getCode' => '0x60006000',
                    'web3_sha3' => '0x12345678'.str_repeat('0', 56),
                    'eth_getTransactionReceipt' => [
                        'transactionHash' => self::TRANSACTION,
                        'blockNumber' => '0x10',
                        'blockHash' => '0x'.str_repeat('c', 64),
                        'status' => '0x1',
                    ],
                    'eth_getBlockByNumber' => ['hash' => '0x'.str_repeat('c', 64)],
                    'eth_getTransactionByHash' => [
                        'hash' => self::TRANSACTION,
                        'to' => self::CONTRACT,
                        'from' => self::SENDER,
                        'blockHash' => '0x'.str_repeat('c', 64),
                        'input' => '0x12345678'.self::PAYLOAD,
                    ],
                    'eth_call' => '0x'.str_repeat('0', 63).'1',
                ];
                $results = array_replace($results, $overrides);
                $this->assertArrayHasKey($request['method'], $results);

                return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => $results[$request['method']]]);
            },
            'http://signer.test' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => self::TRANSACTION]),
        ]);
    }

    public function test_legacy_driver_rejects_submission_before_contacting_the_node_or_signer(): void
    {
        $this->fakeNetwork();

        try {
            app(BlockchainDriver::class)->anchor(self::PAYLOAD);
            $this->fail('Legacy network submission was allowed.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('Legacy network submission is disabled', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_reads_confirmed_hash_from_the_transaction_and_contract(): void
    {
        $this->fakeNetwork();

        $result = app(BesuBlockchainDriver::class)->receipt(self::TRANSACTION, self::CONTRACT);

        $this->assertSame(['payload_hash' => self::PAYLOAD, 'block_number' => 16, 'successful' => true], $result);
        Http::assertSent(fn (Request $request): bool => $request['method'] === 'eth_call'
            && $request['params'][1] === '0x10');
    }

    public function test_pending_transaction_returns_no_confirmation(): void
    {
        $this->fakeNetwork(['eth_getTransactionReceipt' => null]);

        $this->assertNull(app(BesuBlockchainDriver::class)->receipt(self::TRANSACTION, self::CONTRACT));
        Http::assertSentCount(2);
    }

    public function test_failed_receipt_is_not_successful_evidence(): void
    {
        $this->fakeNetwork(['eth_getTransactionReceipt' => [
            'transactionHash' => self::TRANSACTION, 'blockNumber' => '0x10',
            'blockHash' => '0x'.str_repeat('c', 64), 'status' => '0x0',
        ]]);

        $result = app(BesuBlockchainDriver::class)->receipt(self::TRANSACTION, self::CONTRACT);

        $this->assertFalse($result['successful']);
        Http::assertNotSent(fn (Request $request): bool => $request['method'] === 'eth_call');
    }

    public function test_wrong_chain_prevents_legacy_receipt_verification(): void
    {
        $this->fakeNetwork(['eth_chainId' => '0x1']);

        try {
            app(BesuBlockchainDriver::class)->receipt(self::TRANSACTION, self::CONTRACT);
            $this->fail('Wrong-chain verification was allowed.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        Http::assertSentCount(1);
    }

    #[DataProvider('invalidEvidence')]
    public function test_rejects_untrusted_network_evidence(array $overrides): void
    {
        $this->fakeNetwork($overrides);
        $this->expectException(HttpException::class);

        app(BesuBlockchainDriver::class)->receipt(self::TRANSACTION, self::CONTRACT);
    }

    public static function invalidEvidence(): array
    {
        return [
            'wrong contract' => [['eth_getTransactionByHash' => [
                'hash' => self::TRANSACTION, 'to' => self::SENDER, 'from' => self::SENDER,
                'input' => '0x12345678'.self::PAYLOAD, 'blockHash' => '0x'.str_repeat('c', 64),
            ]]],
            'unknown transaction' => [['eth_getTransactionByHash' => null]],
            'wrong signer' => [['eth_getTransactionByHash' => [
                'hash' => self::TRANSACTION, 'to' => self::CONTRACT, 'from' => self::CONTRACT,
                'input' => '0x12345678'.self::PAYLOAD, 'blockHash' => '0x'.str_repeat('c', 64),
            ]]],
            'wrong method' => [['eth_getTransactionByHash' => [
                'hash' => self::TRANSACTION, 'to' => self::CONTRACT, 'from' => self::SENDER,
                'input' => '0x87654321'.self::PAYLOAD, 'blockHash' => '0x'.str_repeat('c', 64),
            ]]],
            'noncanonical block' => [['eth_getBlockByNumber' => ['hash' => '0x'.str_repeat('d', 64)]]],
            'hash absent from contract' => [['eth_call' => '0x'.str_repeat('0', 64)]],
            'malformed receipt' => [['eth_getTransactionReceipt' => ['status' => '0x1']]],
            'invalid field types' => [['eth_getTransactionReceipt' => [
                'transactionHash' => [], 'blockNumber' => [], 'blockHash' => [], 'status' => '0x1',
            ]]],
        ];
    }

    public function test_rpc_errors_do_not_expose_remote_error_details(): void
    {
        Http::fake(['http://besu.test' => Http::response([
            'jsonrpc' => '2.0', 'id' => 1, 'error' => ['message' => 'sensitive upstream detail'],
        ])]);

        try {
            app(BesuBlockchainDriver::class)->receipt(self::TRANSACTION, self::CONTRACT);
            $this->fail('RPC error was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertStringNotContainsString('sensitive upstream detail', $exception->getMessage());
        }
    }

    public function test_connection_failure_does_not_record_confirmation(): void
    {
        Http::fake(['http://besu.test' => Http::failedConnection()]);
        $this->expectException(HttpException::class);

        app(BesuBlockchainDriver::class)->receipt(self::TRANSACTION, self::CONTRACT);
    }

    public function test_unknown_driver_does_not_silently_simulate_anchoring(): void
    {
        config(['blockchain.driver' => 'besuu']);
        $this->expectException(\InvalidArgumentException::class);

        app(BlockchainDriver::class);
    }
}
