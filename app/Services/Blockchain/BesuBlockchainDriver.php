<?php

namespace App\Services\Blockchain;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BesuBlockchainDriver implements OnChainBlockchainDriver
{
    public function name(): string
    {
        return 'besu';
    }

    public function anchor(string $payloadHash): array
    {
        abort(422, 'Legacy network submission is disabled. Use versioned payroll registration for new blockchain proofs.');
    }

    public function receipt(string $transactionId, string $contractAddress): ?array
    {
        abort_unless(preg_match('/^0x[0-9a-f]{64}$/i', $transactionId), 503, 'Invalid stored Besu transaction ID.');
        $contract = $this->address($contractAddress);
        abort_unless(strcasecmp($contract, (string) config('blockchain.contract_address')) === 0, 503, 'Configure the original Besu contract before verifying this anchor.');
        $this->checkNetwork();
        $receipt = $this->rpc('eth_getTransactionReceipt', [$transactionId]);

        if ($receipt === null) {
            return null;
        }

        abort_unless(is_array($receipt)
            && isset($receipt['transactionHash'], $receipt['blockNumber'], $receipt['blockHash'], $receipt['status'])
            && is_string($receipt['transactionHash']) && is_string($receipt['blockNumber'])
            && is_string($receipt['blockHash'])
            && strcasecmp($receipt['transactionHash'], $transactionId) === 0
            && preg_match('/^0x[0-9a-f]{1,15}$/i', $receipt['blockNumber'])
            && in_array($receipt['status'], ['0x0', '0x1'], true), 503, 'Besu returned an invalid transaction receipt.');

        $block = $this->rpc('eth_getBlockByNumber', [$receipt['blockNumber'], false]);
        abort_unless(is_array($block) && isset($block['hash']) && $block['hash'] === $receipt['blockHash'], 503, 'The transaction is not in the current Besu chain.');
        $transaction = $this->rpc('eth_getTransactionByHash', [$transactionId]);
        $selector = $this->selector('anchor(bytes32)');
        abort_unless(is_array($transaction)
            && isset($transaction['hash'], $transaction['to'], $transaction['from'], $transaction['input'], $transaction['blockHash'])
            && is_string($transaction['hash']) && is_string($transaction['to'])
            && is_string($transaction['from']) && is_string($transaction['input'])
            && strcasecmp($transaction['hash'], $transactionId) === 0
            && strcasecmp($transaction['to'], $contract) === 0
            && strcasecmp($transaction['from'], (string) config('blockchain.besu.from_address')) === 0
            && $transaction['blockHash'] === $receipt['blockHash']
            && preg_match('/^'.preg_quote($selector, '/').'[a-f0-9]{64}$/i', $transaction['input']), 503, 'The Besu transaction does not match the configured payroll contract and signer.');

        $hash = strtolower(substr($transaction['input'], 10));

        if ($receipt['status'] === '0x1') {
            $stored = $this->rpc('eth_call', [[
                'to' => $contract,
                'data' => $this->selector('anchored(bytes32)').$hash,
            ], $receipt['blockNumber']]);
            abort_unless($stored === '0x'.str_repeat('0', 63).'1', 503, 'The payroll hash is not recorded in the Besu contract.');
        }

        return [
            'payload_hash' => $hash,
            'block_number' => (int) hexdec($receipt['blockNumber']),
            'successful' => $receipt['status'] === '0x1',
        ];
    }

    private function checkNetwork(): void
    {
        $chain = $this->rpc('eth_chainId', []);
        abort_unless(is_string($chain) && strtolower($chain) === '0x'.dechex((int) config('blockchain.besu.chain_id')), 503, 'Besu chain ID does not match configuration.');
    }

    private function address(string $address): string
    {
        abort_unless(preg_match('/^0x[0-9a-f]{40}$/i', $address) && $address !== '0x'.str_repeat('0', 40), 503, 'Configure valid Besu contract and sender addresses.');

        return $address;
    }

    private function selector(string $signature): string
    {
        $hash = $this->rpc('web3_sha3', ['0x'.bin2hex($signature)]);
        abort_unless(is_string($hash) && preg_match('/^0x[0-9a-f]{64}$/i', $hash), 503, 'Besu returned an invalid method signature hash.');

        return substr($hash, 0, 10);
    }

    /**
     * @param  array<int, mixed>  $parameters
     */
    private function rpc(string $method, array $parameters): mixed
    {
        $url = (string) config('blockchain.besu.rpc_url');
        abort_unless(filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true), 503, 'Configure a valid Besu RPC URL.');

        try {
            $response = Http::connectTimeout(3)->timeout(10)->post($url, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $parameters,
            ]);
        } catch (ConnectionException) {
            abort(503, 'The Besu node is unavailable. Try checking the existing transaction again later.');
        }

        $body = $response->json();
        abort_unless($response->successful() && is_array($body)
            && ($body['jsonrpc'] ?? null) === '2.0' && ($body['id'] ?? null) === 1
            && ! array_key_exists('error', $body) && array_key_exists('result', $body), 503,
            'The Besu node could not complete the requested operation.');

        return $body['result'];
    }
}
