<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\Blockchain\BesuStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BesuStatusIndicatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
        config(['besu.enabled' => true, 'besu.rpc_url' => 'http://127.0.0.1:18545', 'besu.timeout' => 1, 'besu.chain_id' => 20261030]);
        Http::preventStrayRequests();
    }

    private function actingUser(): void
    {
        $user = User::factory()->create(['role' => Role::Administrator]);
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function healthy(): void
    {
        Http::fake(fn ($request) => Http::response(['jsonrpc' => '2.0', 'id' => $request['id'], 'result' => match ($request['method']) {
            'eth_chainId' => '0x13528a6',
            'eth_getBlockByNumber' => ['number' => '0x2a', 'hash' => '0x'.str_repeat('a', 64)],
            default => null,
        }]));
    }

    public function test_indicator_shows_ledger_state_without_probing_when_blockchain_is_disabled(): void
    {
        config(['besu.enabled' => false]);
        Http::fake();
        $this->actingUser();
        // The ledger indicator is independent of external anchoring and does not claim online status.
        $this->get('/dashboard')->assertOk()->assertSee('Ledger Operational')->assertSee('Not configured')->assertDontSee('Blockchain online');
        Http::assertNothingSent();
    }

    public function test_indicator_is_visible_for_all_roles_but_the_management_link_is_admin_only(): void
    {
        $user = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $this->get('/dashboard')->assertOk()->assertSee('Ledger Operational')->assertDontSee(route('besu-operations.index'));

        $this->actingUser();
        $this->get('/dashboard')->assertOk()->assertSee(route('besu-operations.index'));
    }

    public function test_indicator_reports_a_reachable_node(): void
    {
        $this->healthy();
        $this->actingUser();
        $this->assertSame(['online' => true, 'block_number' => 42], app(BesuStatus::class)->check());
        $this->get('/dashboard')->assertOk()->assertSee('Ledger Operational')->assertSee('Reachable');
    }

    public function test_indicator_reports_an_unreachable_node_without_leaking_details(): void
    {
        Http::fake(fn () => throw new ConnectionException('secret provider details'));
        $this->actingUser();
        $this->assertSame(['online' => false, 'block_number' => null], app(BesuStatus::class)->check());
        $this->get('/dashboard')->assertOk()->assertSee('Ledger Operational')->assertSee('Unreachable')->assertDontSee('secret');
    }

    public function test_indicator_treats_a_different_chain_as_offline(): void
    {
        Http::fake(fn ($request) => Http::response(['jsonrpc' => '2.0', 'id' => $request['id'], 'result' => '0x1']));
        $this->assertSame(['online' => false, 'block_number' => null], app(BesuStatus::class)->check());
    }

    public function test_probe_result_is_cached(): void
    {
        $this->healthy();
        $status = app(BesuStatus::class);
        $status->check();
        $status->check();
        Http::assertSentCount(2);
    }
}
