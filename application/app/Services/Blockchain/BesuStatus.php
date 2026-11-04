<?php

namespace App\Services\Blockchain;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lightweight reachability probe for the workspace navbar.
 *
 * Unlike {@see BesuRegistry::health()}, this never verifies the contract, writer
 * authorization or genesis hash, so it stays cheap enough to render on every page.
 * The result is cached briefly to keep the global layout off the RPC hot path and
 * to avoid hammering a node that is down.
 */
class BesuStatus
{
    private const CACHE_KEY = 'besu:node-status';

    private const FRESH_SECONDS = 20;

    public function __construct(private BesuRpcClient $rpc) {}

    /** Whether blockchain anchoring is configured for this installation. */
    public function enabled(): bool
    {
        return (bool) config('besu.enabled');
    }

    /**
     * Report whether the configured node is reachable on the expected chain.
     * Never throws: an unreachable or misconfigured node is simply "offline".
     *
     * @return array{online: bool, block_number: int|null}
     */
    public function check(): array
    {
        if (! $this->enabled()) {
            return ['online' => false, 'block_number' => null];
        }

        return Cache::remember(self::CACHE_KEY, now()->addSeconds(self::FRESH_SECONDS), function (): array {
            try {
                $chainId = $this->rpc->request('eth_chainId');
                if (! is_string($chainId) || ! preg_match('/\A0x(?:0|[1-9a-fA-F][a-fA-F0-9]{0,12})\z/', $chainId)
                    || (int) hexdec(substr($chainId, 2)) !== (int) config('besu.chain_id')) {
                    return ['online' => false, 'block_number' => null];
                }

                $block = $this->rpc->request('eth_getBlockByNumber', ['latest', false]);
                if (! is_array($block) || ! is_string($block['number'] ?? null)
                    || ! preg_match('/\A0x(?:0|[1-9a-fA-F][a-fA-F0-9]{0,12})\z/', $block['number'])) {
                    return ['online' => false, 'block_number' => null];
                }

                return ['online' => true, 'block_number' => (int) hexdec(substr($block['number'], 2))];
            } catch (Throwable) {
                return ['online' => false, 'block_number' => null];
            }
        });
    }
}
