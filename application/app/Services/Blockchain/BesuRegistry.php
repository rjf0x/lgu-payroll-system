<?php

namespace App\Services\Blockchain;

class BesuRegistry
{
    public function __construct(private BesuRpcClient $rpc, private BesuBridge $bridge) {}

    private function settings(): array
    {
        $settings = config('besu');
        if (! $settings['enabled'] || $settings['chain_id'] !== 20261030) {
            throw new BesuConnectionException('invalid_configuration');
        }
        foreach (['contract_address', 'administrator', 'writer', 'genesis_hash', 'runtime_code_hash'] as $field) {
            $length = in_array($field, ['genesis_hash', 'runtime_code_hash'], true) ? 64 : 40;
            if (! is_string($settings[$field]) || ! preg_match('/\A0x[a-fA-F0-9]{'.$length.'}\z/', $settings[$field])
                || preg_match('/\A0x0+\z/', $settings[$field])) {
                throw new BesuConnectionException('invalid_configuration');
            }
        }

        return $settings;
    }

    private function quantity(mixed $hex): int
    {
        if (! is_string($hex) || ! preg_match('/\A0x(?:0|[1-9a-fA-F][a-fA-F0-9]{0,12})\z/', $hex)) {
            throw new BesuConnectionException('invalid_rpc_response');
        }

        return (int) hexdec(substr($hex, 2));
    }

    private function call(string $method, array $args, string $block): array
    {
        $encoded = $this->bridge->invoke('encode', ['method' => $method, 'args' => $args]);
        if (! is_string($encoded['data'] ?? null) || ! preg_match('/\A0x[a-f0-9]+\z/i', $encoded['data'])) {
            throw new BesuConnectionException('bridge_unavailable');
        }
        $value = $this->rpc->request('eth_call', [['to' => config('besu.contract_address'), 'data' => $encoded['data']], $block]);
        if (! is_string($value) || strlen($value) > 8194 || ! preg_match('/\A0x(?:[a-f0-9]{2})+\z/i', $value)) {
            throw new BesuConnectionException('invalid_contract');
        }

        return $this->bridge->invoke('decode', ['method' => $method, 'value' => $value]);
    }

    /** Identity checks always apply; write operations also require current writer authorization. */
    public function health(bool $requireWriter = true): array
    {
        $settings = $this->settings();
        if ($this->quantity($this->rpc->request('eth_chainId')) !== $settings['chain_id']) {
            throw new BesuConnectionException('wrong_chain');
        }
        $client = $this->rpc->request('web3_clientVersion');
        if (! is_string($client) || ! preg_match('/\Abesu\/[\x20-\x7E]{1,110}\z/i', $client)) {
            throw new BesuConnectionException('wrong_client');
        }
        $genesis = $this->rpc->request('eth_getBlockByNumber', ['0x0', false]);
        if (! is_array($genesis) || ! is_string($genesis['hash'] ?? null) || strtolower($genesis['hash']) !== strtolower($settings['genesis_hash'])) {
            throw new BesuConnectionException('wrong_genesis');
        }
        $block = $this->rpc->request('eth_getBlockByNumber', ['latest', false]);
        if (! is_array($block) || ! is_string($block['hash'] ?? null) || ! preg_match('/\A0x[a-f0-9]{64}\z/i', $block['hash'])) {
            throw new BesuConnectionException('invalid_rpc_response');
        }
        $height = $this->quantity($block['number'] ?? null);
        $code = $this->rpc->request('eth_getCode', [$settings['contract_address'], $block['number']]);
        if (! is_string($code) || strlen($code) > 65538 || ! preg_match('/\A0x(?:[a-f0-9]{2})+\z/i', $code)) {
            throw new BesuConnectionException('invalid_contract');
        }
        $hash = $this->bridge->invoke('codeHash', ['code' => $code])['hash'] ?? null;
        if (! is_string($hash) || strtolower($hash) !== strtolower($settings['runtime_code_hash'])) {
            throw new BesuConnectionException('invalid_contract');
        }
        $admin = $this->call('administrator', [], $block['number'])['value'] ?? null;
        if (! is_string($admin) || strtolower($admin) !== strtolower($settings['administrator'])
            || ($requireWriter && ($this->call('writers', [$settings['writer']], $block['number'])['value'] ?? null) !== true)) {
            throw new BesuConnectionException('writer_unauthorized');
        }
        $again = $this->rpc->request('eth_getBlockByNumber', [$block['number'], false]);
        if (! is_array($again) || ($again['hash'] ?? null) !== $block['hash']) {
            throw new BesuConnectionException('chain_changed');
        }

        return ['client' => $client, 'chain_id' => $settings['chain_id'], 'contract' => $settings['contract_address'],
            'writer' => $settings['writer'], 'block_number' => $height, 'block_hash' => $block['hash'], 'block_tag' => $block['number']];
    }

    /** Contract lookup only, not a payroll integrity verdict or receipt confirmation. */
    public function lookup(string $reference): array
    {
        if (! preg_match('/\A0x[a-f0-9]{64}\z/', $reference)) {
            throw new BesuConnectionException('invalid_reference');
        }
        $health = $this->health(false);
        $row = $this->call('lookup', [$reference], $health['block_tag']);
        if (! is_bool($row['exists'] ?? null) || ! is_string($row['sha256'] ?? null)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $row['sha256'])
            || ! is_string($row['writer'] ?? null) || ! preg_match('/\A0x[a-f0-9]{40}\z/i', $row['writer'])
            || ! is_string($row['anchoredAt'] ?? null) || ! ctype_digit($row['anchoredAt'])) {
            throw new BesuConnectionException('invalid_contract');
        }

        return $row;
    }

    /** Offline signing preparation only. No broadcast method exists in this phase. */
    public function sign(string $reference, string $sha256, ?int $reservedNonce = null): array
    {
        if (! preg_match('/\A0x[a-f0-9]{64}\z/', $reference) || $reference === '0x'.str_repeat('0', 64)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $sha256) || $sha256 === str_repeat('0', 64)) {
            throw new BesuConnectionException('invalid_reference');
        }
        $health = $this->health();
        $nonce = $reservedNonce ?? $this->quantity($this->rpc->request('eth_getTransactionCount', [$health['writer'], 'pending']));
        if ($nonce < 0 || $nonce > 4503599627370495) {
            throw new BesuConnectionException('invalid_nonce');
        }
        $signed = $this->bridge->invoke('sign', [
            'chainId' => $health['chain_id'], 'contract' => $health['contract'], 'writer' => $health['writer'],
            'nonce' => $nonce, 'reference' => $reference, 'sha256' => $sha256,
            'keystorePath' => config('besu.keystore_path'), 'passwordPath' => config('besu.password_path'),
        ]);
        if (! is_string($signed['raw'] ?? null) || ! preg_match('/\A0x[a-f0-9]{1,8192}\z/i', $signed['raw'])
            || ! is_string($signed['hash'] ?? null) || ! preg_match('/\A0x[a-f0-9]{64}\z/i', $signed['hash'])
            || ! is_string($signed['from'] ?? null) || strtolower($signed['from']) !== strtolower($health['writer']) || ($signed['nonce'] ?? null) !== $nonce) {
            throw new BesuConnectionException('bridge_unavailable');
        }

        return $signed;
    }
}
