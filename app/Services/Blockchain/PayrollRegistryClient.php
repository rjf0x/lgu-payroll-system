<?php

namespace App\Services\Blockchain;

use App\Models\PayrollRegistration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayrollRegistryClient
{
    /** @return array{chain_id:string, genesis_hash:string, contract_address:string, code_hash:string, signer_address:string, deployment_block:int, bridge_url:string} */
    public function target(): array
    {
        $config = config('blockchain.registry');
        $chain = (string) $config['chain_id'];
        $block = (string) $config['deployment_block'];
        $url = rtrim((string) $config['bridge_url'], '/');
        $this->check(preg_match('/^[1-9][0-9]*$/D', $chain) === 1 && preg_match('/^(0|[1-9][0-9]*)$/D', $block) === 1
            && strlen($block) <= 16 && (int) $block <= 9007199254740991, 'INVALID_REGISTRY_CONFIG');
        $this->check(filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null
            && parse_url($url, PHP_URL_QUERY) === null && parse_url($url, PHP_URL_FRAGMENT) === null, 'INVALID_REGISTRY_CONFIG');
        $target = ['chain_id' => $chain];
        foreach (['genesis_hash' => 64, 'contract_address' => 40, 'code_hash' => 64, 'signer_address' => 40] as $key => $length) {
            $value = strtolower((string) $config[$key]);
            $this->check(preg_match('/^0x[a-f0-9]{'.$length.'}$/D', $value) === 1 && trim(substr($value, 2), '0') !== '', 'INVALID_REGISTRY_CONFIG');
            $target[$key] = $value;
        }
        $target['deployment_block'] = (int) $block;
        $target['bridge_url'] = $url;

        return $target;
    }

    /** @return array{status:string, transaction_hash:?string, evidence:?array<string, mixed>} */
    public function synchronize(PayrollRegistration $registration): array
    {
        $target = $this->target();
        $this->check($this->sameFields($target, $registration->target_network), 'REGISTRY_TARGET_CHANGED');
        $network = $target;
        unset($network['bridge_url']);
        $health = $this->request('GET', $target['bridge_url'].'/health');
        $this->check(($health['status'] ?? null) === 'READY' && $this->sameFields($network, $health['network'] ?? null), 'BRIDGE_NETWORK_MISMATCH');
        $path = $target['bridge_url'].'/registrations/'.$registration->organization_id.'/'.$registration->payroll_reference.'/'.$registration->revision;
        $read = $this->request('GET', $path);
        $evidence = $read['registration'] ?? null;
        if ($evidence !== null) {
            $evidence = $this->validateEvidence($registration, $evidence, $network);
            $matches = hash_equals($registration->payload_hash, strtolower($evidence['payload_hash']))
                && $evidence['hash_schema_version'] === $registration->hash_schema_version;

            return ['status' => $matches ? PayrollRegistration::CONFIRMED : PayrollRegistration::CONFLICT,
                'transaction_hash' => $evidence['transaction_hash'],
                'evidence' => array_intersect_key($evidence, array_flip(['organization_id', 'payroll_reference', 'revision', 'payload_hash', 'hash_schema_version',
                    'chain_id', 'genesis_hash', 'contract_address', 'transaction_hash', 'block_hash', 'block_number', 'registered_at', 'registrar']))];
        }
        $submission = $read['submission'] ?? $this->request('POST', $target['bridge_url'].'/registrations', $registration->bridgeRequest());
        $this->check(is_array($submission), 'INVALID_BRIDGE_RESPONSE');
        foreach ($registration->bridgeRequest() as $key => $value) {
            $this->check(($submission[$key] ?? null) === $value, 'INVALID_BRIDGE_RESPONSE');
        }
        $status = $submission['status'] ?? null;
        $this->check(in_array($status, [...PayrollRegistration::ACTIVE, PayrollRegistration::FAILED, PayrollRegistration::CONFLICT], true), 'INVALID_BRIDGE_RESPONSE');
        $transaction = $submission['transaction_hash'] ?? null;
        $this->check($transaction === null || (is_string($transaction) && preg_match('/^0x[a-fA-F0-9]{64}$/D', $transaction) === 1), 'INVALID_BRIDGE_RESPONSE');
        $this->check(! in_array($status, [PayrollRegistration::SUBMITTED, PayrollRegistration::FAILED], true) || $transaction !== null, 'INVALID_BRIDGE_RESPONSE');
        $failure = null;
        if ($status === PayrollRegistration::FAILED) {
            $failure = $submission['evidence'] ?? null;
            $this->check(is_array($failure) && ($failure['successful'] ?? null) === false
                && ($failure['transaction_hash'] ?? null) === $transaction
                && is_int($failure['block_number'] ?? null) && $failure['block_number'] >= $target['deployment_block']
                && is_string($failure['block_hash'] ?? null) && preg_match('/^0x[a-fA-F0-9]{64}$/D', $failure['block_hash']) === 1, 'INVALID_BRIDGE_EVIDENCE');
            $failure = array_intersect_key($failure, array_flip(['successful', 'transaction_hash', 'block_number', 'block_hash']));
        }

        return ['status' => $status, 'transaction_hash' => $transaction, 'evidence' => $failure];
    }

    /** Read-only verification deliberately does not require capture to be enabled or a signer to be available.
     * @return array{registration:?array<string, mixed>, submission:?array<string, mixed>}
     */
    public function read(PayrollRegistration $registration): array
    {
        $this->check(preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/D', $registration->organization_id) === 1
            && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $registration->payroll_reference) === 1
            && $registration->revision > 0 && $registration->revision <= 4294967295, 'INVALID_BRIDGE_EVIDENCE');
        $url = rtrim((string) config('blockchain.registry.bridge_url'), '/');
        $this->check(filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null
            && parse_url($url, PHP_URL_QUERY) === null && parse_url($url, PHP_URL_FRAGMENT) === null, 'INVALID_REGISTRY_CONFIG');
        $read = $this->request('GET', $url.'/registrations/'.$registration->organization_id.'/'.$registration->payroll_reference.'/'.$registration->revision);
        $expected = $registration->target_network;
        $this->check(is_array($expected), 'INVALID_BRIDGE_EVIDENCE');
        unset($expected['bridge_url'], $expected['signer_address']);
        $network = $read['network'] ?? null;
        $this->check(is_array($network), 'INVALID_BRIDGE_EVIDENCE');
        unset($network['signer_address']);
        $this->check($this->sameFields($expected, $network), 'BRIDGE_NETWORK_MISMATCH');
        $this->check(array_key_exists('registration', $read) && array_key_exists('submission', $read), 'INVALID_BRIDGE_RESPONSE');
        $evidence = $read['registration'] === null ? null : $this->validateEvidence($registration, $read['registration'], $expected);
        $submission = $read['submission'];
        if ($submission !== null) {
            $this->check(is_array($submission), 'INVALID_BRIDGE_RESPONSE');
            foreach (['organization_id', 'payroll_reference', 'revision'] as $key) {
                $this->check(($submission[$key] ?? null) === $registration->{$key}, 'INVALID_BRIDGE_RESPONSE');
            }
            $this->check(in_array($submission['status'] ?? null, [...PayrollRegistration::ACTIVE, PayrollRegistration::CONFIRMED, PayrollRegistration::FAILED, PayrollRegistration::CONFLICT], true), 'INVALID_BRIDGE_RESPONSE');
        }

        return ['registration' => $evidence, 'submission' => $submission === null ? null : ['status' => $submission['status']]];
    }

    /** @param array<string, mixed> $network
     * @return array<string, mixed>
     */
    private function validateEvidence(PayrollRegistration $registration, mixed $evidence, array $network): array
    {
        $this->check(is_array($evidence), 'INVALID_BRIDGE_EVIDENCE');
        foreach (['organization_id', 'payroll_reference', 'revision'] as $key) {
            $this->check(($evidence[$key] ?? null) === $registration->{$key}, 'INVALID_BRIDGE_EVIDENCE');
        }
        foreach (['chain_id', 'genesis_hash', 'contract_address'] as $key) {
            $this->check(($evidence[$key] ?? null) === ($network[$key] ?? null), 'INVALID_BRIDGE_EVIDENCE');
        }
        foreach (['transaction_hash', 'block_hash', 'payload_hash', 'registrar'] as $key) {
            $length = $key === 'registrar' ? 40 : 64;
            $prefix = $key === 'payload_hash' ? '' : '0x';
            $this->check(is_string($evidence[$key] ?? null) && preg_match('/^'.$prefix.'[a-fA-F0-9]{'.$length.'}$/D', $evidence[$key]) === 1, 'INVALID_BRIDGE_EVIDENCE');
            if (in_array($key, ['payload_hash', 'registrar'], true)) {
                $this->check(trim(substr($evidence[$key], strlen($prefix)), '0') !== '', 'INVALID_BRIDGE_EVIDENCE');
            }
        }
        $this->check(is_int($evidence['block_number'] ?? null) && $evidence['block_number'] >= ($network['deployment_block'] ?? PHP_INT_MAX)
            && is_string($evidence['registered_at'] ?? null) && preg_match('/^[0-9]+$/D', $evidence['registered_at']) === 1
            && is_int($evidence['hash_schema_version'] ?? null), 'INVALID_BRIDGE_EVIDENCE');
        $evidence['payload_hash'] = strtolower($evidence['payload_hash']);

        return array_intersect_key($evidence, array_flip(['organization_id', 'payroll_reference', 'revision', 'payload_hash', 'hash_schema_version',
            'chain_id', 'genesis_hash', 'contract_address', 'transaction_hash', 'block_hash', 'block_number', 'registered_at', 'registrar']));
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, array $data = []): array
    {
        $token = (string) config('blockchain.registry.bridge_token');
        $this->check(strlen($token) >= 32, 'INVALID_BRIDGE_CREDENTIALS');
        $response = Http::withToken($token)->acceptJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()
            ->send($method, $url, $method === 'POST' ? ['json' => $data] : []);
        if (! $response->successful() && in_array($response->json('error'), ['INVALID_EVIDENCE', 'NETWORK_OR_CONTRACT_MISMATCH'], true)) {
            throw new RuntimeException('INVALID_BRIDGE_EVIDENCE');
        }
        $this->check($response->successful(), 'BRIDGE_REQUEST_FAILED');
        $body = $response->json();
        $this->check(is_array($body), 'INVALID_BRIDGE_RESPONSE');

        return $body;
    }

    private function check(bool $valid, string $code): void
    {
        if (! $valid) {
            throw new RuntimeException($code);
        }
    }

    /** @param array<string, mixed> $expected */
    private function sameFields(array $expected, mixed $actual): bool
    {
        if (! is_array($actual) || count($actual) !== count($expected)) {
            return false;
        }
        foreach ($expected as $key => $value) {
            if (($actual[$key] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
