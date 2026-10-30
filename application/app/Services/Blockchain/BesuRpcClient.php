<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class BesuRpcClient
{
    /** General requests stay read-only; sending requires the separate journaled broadcast path. */
    private const METHODS = ['eth_chainId', 'web3_clientVersion', 'eth_getBlockByNumber', 'eth_getCode', 'eth_call', 'eth_getTransactionCount', 'eth_getTransactionReceipt', 'eth_getTransactionByHash'];

    public function request(string $method, array $params = []): mixed
    {
        return $this->perform($method, $params, false);
    }

    /** Only the durable outbox processor may call this after journaling signed bytes. */
    public function broadcast(string $raw): string
    {
        if (! preg_match('/\A0x[a-f0-9]+\z/i', $raw) || strlen($raw) > 8194) {
            throw new BesuConnectionException('invalid_transaction');
        }
        $hash = $this->perform('eth_sendRawTransaction', [$raw], true);
        if (! is_string($hash) || ! preg_match('/\A0x[a-f0-9]{64}\z/i', $hash)) {
            throw new BesuConnectionException('invalid_rpc_response');
        }

        return $hash;
    }

    private function perform(string $method, array $params, bool $broadcast): mixed
    {
        $url = config('besu.rpc_url');
        $parts = is_string($url) ? parse_url($url) : false;
        $timeout = config('besu.timeout');
        if (! config('besu.enabled') || ! is_array($parts) || ($parts['scheme'] ?? '') !== 'http'
            || ! in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost'], true)
            || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || ($parts['path'] ?? '/') !== '/'
            || ! is_int($timeout) || $timeout < 1 || $timeout > 10
            || (! in_array($method, self::METHODS, true) && ! ($broadcast && $method === 'eth_sendRawTransaction'))) {
            throw new BesuConnectionException('invalid_configuration');
        }
        $id = (string) Str::uuid();
        try {
            $options = ['allow_redirects' => false, 'proxy' => ''];
            if ($parts['host'] === 'localhost') {
                $options['curl'] = [CURLOPT_RESOLVE => ['localhost:'.($parts['port'] ?? 80).':127.0.0.1']];
            }
            $response = Http::withOptions($options)->connectTimeout(min(3, $timeout))->timeout($timeout)
                ->acceptJson()->post($url, ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
            if ($response->status() !== 200 || strlen($response->body()) > 262144) {
                throw new BesuConnectionException('rpc_unavailable');
            }
            $data = $response->json();
            if (! is_array($data) || ($data['jsonrpc'] ?? null) !== '2.0' || ($data['id'] ?? null) !== $id
                || array_key_exists('error', $data) || ! array_key_exists('result', $data)) {
                throw new BesuConnectionException('invalid_rpc_response');
            }

            return $data['result'];
        } catch (BesuConnectionException $error) {
            throw $error;
        } catch (Throwable) {
            throw new BesuConnectionException('rpc_unavailable');
        }
    }
}
