<?php

namespace Tests\Feature;

use App\Services\Blockchain\BesuBridge;
use App\Services\Blockchain\BesuConnectionException;
use App\Services\Blockchain\BesuRegistry;
use App\Services\Blockchain\BesuRpcClient;
use App\Services\Blockchain\BesuTransactionVerifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BesuConnectionTest extends TestCase
{
    private array $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deployment = json_decode(file_get_contents(base_path('../blockchain/contracts/deployments/local.json')), true);
        config(['besu.enabled' => true, 'besu.rpc_url' => 'http://127.0.0.1:18545', 'besu.timeout' => 1,
            'besu.chain_id' => 20261030, 'besu.genesis_hash' => $this->deployment['genesisHash'],
            'besu.contract_address' => $this->deployment['address'], 'besu.administrator' => $this->deployment['administrator'],
            'besu.writer' => $this->deployment['writer'], 'besu.runtime_code_hash' => $this->deployment['runtimeCodeHash']]);
        Http::preventStrayRequests();
    }

    private function response(mixed $result, $request)
    {
        return Http::response(['jsonrpc' => '2.0', 'id' => $request['id'], 'result' => $result]);
    }

    public function test_rpc_matches_request_identity(): void
    {
        Http::fake(fn ($request) => $this->response('0x13528a6', $request));
        $this->assertSame('0x13528a6', app(BesuRpcClient::class)->request('eth_chainId'));
    }

    public function test_disabled_external_and_broadcast_requests_are_rejected_before_io(): void
    {
        Http::fake();
        foreach ([['besu.enabled', false, 'eth_chainId'], ['besu.rpc_url', 'https://example.com', 'eth_chainId'],
            ['besu.rpc_url', 'http://localhost@evil.example', 'eth_chainId'], ['besu.rpc_url', 'http://127.0.0.1/?secret=x', 'eth_chainId'],
            ['besu.rpc_url', 'http://127.0.0.1', 'eth_sendRawTransaction']] as [$key, $value, $method]) {
            config(['besu.enabled' => true, 'besu.rpc_url' => 'http://127.0.0.1:18545', $key => $value]);
            try {
                app(BesuRpcClient::class)->request($method);
                $this->fail('Unsafe request accepted');
            } catch (BesuConnectionException $e) {
                $this->assertSame('invalid_configuration', $e->reason);
            }
        }
        Http::assertNothingSent();
    }

    public function test_redirects_errors_and_unmatched_responses_fail_safely(): void
    {
        foreach ([Http::response('', 302, ['Location' => 'http://evil.example']), Http::response('secret provider details', 500),
            Http::response(['jsonrpc' => '2.0', 'id' => 'wrong', 'result' => '0x1']),
            Http::response('not json')] as $response) {
            Http::swap(new Factory);
            Http::fake(fn () => $response);
            try {
                app(BesuRpcClient::class)->request('eth_chainId');
                $this->fail('Invalid response accepted');
            } catch (BesuConnectionException $e) {
                $this->assertStringNotContainsString('secret', $e->getMessage());
                $this->assertNull($e->getPrevious());
            }
        }
    }

    private function healthy(?string $failure = null): void
    {
        $block = ['number' => '0x100', 'hash' => '0x'.str_repeat('a', 64)];
        Http::swap(new Factory);
        Http::fake(function ($request) use ($block, $failure) {
            $result = match ($request['method']) {
                'eth_chainId' => $failure === 'wrong_chain' ? '0x1' : '0x13528a6',
                'web3_clientVersion' => $failure === 'wrong_client' ? 'geth/v1' : 'besu/v26.10.2',
                'eth_getBlockByNumber' => $request['params'][0] === '0x0'
                    ? ['hash' => $failure === 'wrong_genesis' ? [] : $this->deployment['genesisHash']]
                    : ($failure === 'chain_changed' && $request['params'][0] === '0x100' ? [...$block, 'hash' => '0x'.str_repeat('b', 64)] : $block),
                'eth_getCode' => $failure === 'invalid_contract' ? '0x' : '0x6000',
                'eth_call' => '0x00',
                'eth_getTransactionCount' => '0x2',
                'eth_getTransactionReceipt' => ['transactionHash' => '0x'.str_repeat('b', 64),
                    'blockNumber' => $block['number'], 'blockHash' => $block['hash'], 'status' => '0x1'],
                'eth_getTransactionByHash' => ['hash' => '0x'.str_repeat('b', 64)],
            };

            return $this->response($result, $request);
        });
        $bridge = $this->mock(BesuBridge::class);
        $bridge->shouldReceive('invoke')->andReturnUsing(function ($operation, $data) use ($failure) {
            return match ($operation) {
                'codeHash' => ['hash' => $this->deployment['runtimeCodeHash']],
                'encode' => ['data' => '0x12345678'],
                'decode' => match ($data['method']) {
                    'administrator' => ['value' => $this->deployment['administrator']],
                    'writers' => ['value' => $failure !== 'writer_unauthorized'],
                    'lookup' => $failure === 'writer_unauthorized'
                        ? ['sha256' => str_repeat('1', 64), 'writer' => $this->deployment['writer'], 'anchoredAt' => '42', 'exists' => true]
                        : ['sha256' => str_repeat('0', 64), 'writer' => '0x'.str_repeat('0', 40), 'anchoredAt' => '0', 'exists' => false],
                },
                'sign' => ['raw' => '0x1234', 'hash' => '0x'.str_repeat('b', 64), 'from' => $this->deployment['writer'], 'nonce' => 2],
                'verifyMined' => ['valid' => true, 'anchoredAt' => '42'],
            };
        });
    }

    public function test_health_lookup_and_sign_use_read_only_rpc(): void
    {
        $this->healthy();
        $registry = app(BesuRegistry::class);
        $this->assertSame(256, $registry->health()['block_number']);
        $this->assertFalse($registry->lookup('0x'.str_repeat('1', 64))['exists']);
        $this->assertSame(2, $registry->sign('0x'.str_repeat('1', 64), str_repeat('2', 64))['nonce']);
        Http::assertSent(fn ($r) => $r['method'] === 'eth_call' && $r['params'][1] === '0x100');
        Http::assertNotSent(fn ($r) => str_starts_with($r['method'], 'eth_send'));
    }

    public function test_chain_contract_and_authorization_failures_are_rejected(): void
    {
        foreach (['wrong_chain', 'wrong_client', 'wrong_genesis', 'invalid_contract', 'writer_unauthorized', 'chain_changed'] as $failure) {
            $this->healthy($failure);
            app()->forgetInstance(BesuRegistry::class);
            try {
                app(BesuRegistry::class)->health();
                $this->fail('Bad identity accepted');
            } catch (BesuConnectionException $e) {
                $this->assertSame($failure, $e->reason);
            }
        }
    }

    public function test_revoked_writer_history_is_readable_but_health_and_new_signatures_are_rejected(): void
    {
        $this->healthy('writer_unauthorized');
        $registry = app(BesuRegistry::class);
        $this->assertTrue($registry->lookup('0x'.str_repeat('1', 64))['exists']);
        foreach (['health', 'sign'] as $operation) {
            try {
                $operation === 'health' ? $registry->health() : $registry->sign('0x'.str_repeat('1', 64), str_repeat('2', 64));
                $this->fail('Revoked writer was allowed to sign or pass writer health.');
            } catch (BesuConnectionException $e) {
                $this->assertSame('writer_unauthorized', $e->reason);
            }
        }
    }

    public function test_revoked_writer_historical_receipt_confirms_through_real_verifier_and_registry_services(): void
    {
        $this->healthy('writer_unauthorized');
        $result = app(BesuTransactionVerifier::class)->confirm('0x1234', '0x'.str_repeat('b', 64), '0x'.str_repeat('1', 64), str_repeat('1', 64));
        $this->assertSame('confirmed', $result['status']);
        $this->assertSame(256, $result['block_number']);
        Http::assertSent(fn ($request) => $request['method'] === 'eth_getTransactionReceipt');

        // Read mode still rejects an unexpected chain instead of trusting the receipt.
        $this->healthy('wrong_chain');
        $this->expectExceptionMessage('Besu connection check failed (wrong_chain).');
        app(BesuTransactionVerifier::class)->confirm('0x1234', '0x'.str_repeat('b', 64), '0x'.str_repeat('1', 64), str_repeat('1', 64));
    }

    public function test_bridge_hides_subprocess_errors(): void
    {
        Process::fake(['*' => Process::result(output: 'secret input', errorOutput: 'secret password', exitCode: 1)]);
        $this->expectExceptionMessage('Besu connection check failed (bridge_unavailable).');
        app(BesuBridge::class)->invoke('sign', []);
    }

    public function test_command_never_prints_signed_transaction(): void
    {
        $this->healthy();
        $this->assertSame(0, Artisan::call('besu:check', ['--sign' => true]));
        $output = Artisan::output();
        $this->assertStringNotContainsString('0x1234', $output);
        $this->assertTrue(json_decode($output, true)['offline_signature_valid']);
        $this->assertFalse(json_decode($output, true)['broadcast']);
    }

    public function test_connection_timeout_does_not_expose_provider_details(): void
    {
        Http::fake(fn () => throw new ConnectionException('secret provider details'));
        $this->expectExceptionMessage('Besu connection check failed (rpc_unavailable).');
        app(BesuRpcClient::class)->request('eth_chainId');
    }

    public function test_rpc_error_with_matching_id_is_rejected(): void
    {
        Http::fake(fn ($request) => Http::response(['jsonrpc' => '2.0', 'id' => $request['id'], 'error' => ['message' => 'secret']]));
        $this->expectExceptionMessage('Besu connection check failed (invalid_rpc_response).');
        app(BesuRpcClient::class)->request('eth_chainId');
    }

    public function test_invalid_signing_input_does_not_contact_network_or_signer(): void
    {
        Http::fake();
        Process::fake();
        try {
            app(BesuRegistry::class)->sign('0x'.str_repeat('0', 64), str_repeat('1', 64));
            $this->fail('Zero reference accepted');
        } catch (BesuConnectionException $e) {
            $this->assertSame('invalid_reference', $e->reason);
        }
        Http::assertNothingSent();
        Process::assertNothingRan();
    }

    public function test_disabled_command_fails_without_io(): void
    {
        config(['besu.enabled' => false]);
        Http::fake();
        Process::fake();
        $this->artisan('besu:check')->expectsOutputToContain('invalid_configuration')->assertFailed();
        Http::assertNothingSent();
        Process::assertNothingRan();
    }
}
